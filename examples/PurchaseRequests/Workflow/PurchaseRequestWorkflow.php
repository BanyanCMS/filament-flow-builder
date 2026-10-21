<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\ExampleUser;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\PurchaseRequest;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\RequestHistory;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\RequestVote;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Notifications\StepWaitingForYou;
use Banyan\FilamentFlowBuilder\Runtime\FlowRunner;
use Banyan\FilamentFlowBuilder\Runtime\Position;
use Banyan\FilamentFlowBuilder\Steps\ParallelApprovalStep;
use Banyan\FilamentFlowBuilder\Validation\FlowValidator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Everything people DO with a purchase request, in one place: submit it,
 * approve or ask for changes on its current step, retry it when it is stuck,
 * remind people of overdue steps.
 *
 * The plugin's runner decides where a request goes next; this class decides
 * who may act, when a step has enough approvals, what the request's status
 * is, and keeps the audit log (with RecordHistory, which listens to the
 * runner's events). Copy it and change what your process needs.
 */
class PurchaseRequestWorkflow
{
    public function __construct(
        private FlowRunner $runner,
        private ApproverDirectory $directory,
        private PurchaseFlow $definition,
        private FlowValidator $validator,
    ) {}

    /** The requester sends it in (again, after changes were asked for). */
    public function submit(PurchaseRequest $request, int $actorId): void
    {
        if (! in_array($request->status, [PurchaseRequest::DRAFT, PurchaseRequest::CHANGES_REQUESTED], true)) {
            throw new WorkflowException('Only a draft, or a request sent back for changes, can be submitted.');
        }

        $flow = Flow::fromArray($request->approvalFlow->flow ?? []);
        $problems = $this->validator->problems($flow, $this->definition);

        if ($problems !== []) {
            throw new WorkflowException('The approval flow has problems; fix it in the builder first.');
        }

        DB::transaction(function () use ($request, $flow, $actorId): void {
            // A snapshot: changing the template later never moves requests already on their way.
            $request->flow = $flow->toArray();
            $this->log($request, 'submitted', null, $actorId, ['amount' => $request->amount]);
            $this->apply($request, $this->runner->start($this->definition, $flow, $this->context($request, $actorId)));
        });
    }

    public function approve(PurchaseRequest $request, int $actorId, ?string $note = null): void
    {
        $this->vote($request, $actorId, 'approve', $note);
    }

    /** One "changes" from anyone on the step sends the request back to the requester. */
    public function requestChanges(PurchaseRequest $request, int $actorId, string $note): void
    {
        if (trim($note) === '') {
            throw new WorkflowException('Say what needs to change.');
        }

        $this->vote($request, $actorId, 'changes', $note);
    }

    /** A stuck request (nobody could act on its step) tries its step again, after someone was assigned. */
    public function retry(PurchaseRequest $request, int $actorId): void
    {
        $step = $this->stepOf($request);

        if ($request->status !== PurchaseRequest::STUCK || $step === null) {
            throw new WorkflowException('Only a stuck request can be retried.');
        }

        $this->log($request, 'retried', $step, $actorId);
        $this->apply($request, Position::at($step->id));
    }

    /**
     * The people who may act on the request now.
     *
     * @return list<int>
     */
    public function whoCanAct(PurchaseRequest $request): array
    {
        $step = $this->stepOf($request);

        if ($step === null || ! in_array($request->status, [PurchaseRequest::IN_REVIEW, PurchaseRequest::STUCK], true)) {
            return [];
        }

        $ids = [];

        foreach ($this->keysOf($step) as $key) {
            array_push($ids, ...$this->directory->userIds($key));
        }

        // Nobody approves their own request.
        return array_values(array_diff(array_unique($ids), [$request->requester_id]));
    }

    public function canAct(PurchaseRequest $request, int $userId): bool
    {
        return $request->status === PurchaseRequest::IN_REVIEW && in_array($userId, $this->whoCanAct($request), true);
    }

    /** The step the request is waiting at, from its own snapshot of the flow. */
    public function stepOf(PurchaseRequest $request): ?Step
    {
        $position = Position::fromArray($request->position);

        return $position->state === Position::AT_STEP && $position->stepId !== null
            ? Flow::fromArray($request->flow ?? [])->find($position->stepId)
            : null;
    }

    /**
     * Reminds the people of every step that is waiting longer than its "Remind after" days. Run it from the scheduler.
     *
     * @return int how many requests were reminded
     */
    public function remindOverdue(?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $count = 0;

        foreach (PurchaseRequest::query()->where('status', PurchaseRequest::IN_REVIEW)->whereNull('reminded_at')->get() as $request) {
            $step = $this->stepOf($request);

            if ($step?->reminderDays === null || $request->step_reached_at === null || $request->step_reached_at->copy()->addDays($step->reminderDays)->gt($now)) {
                continue;
            }

            $this->notifyApprovers($request, $step, reminder: true);
            $request->forceFill(['reminded_at' => $now])->save();
            $this->log($request, 'reminded', $step, null, ['after_days' => $step->reminderDays]);
            $count++;
        }

        return $count;
    }

    private function vote(PurchaseRequest $request, int $actorId, string $decision, ?string $note): void
    {
        $step = $this->stepOf($request);

        if ($step === null || $request->status !== PurchaseRequest::IN_REVIEW) {
            throw new WorkflowException('This request is not waiting for a decision.');
        }

        if (! $this->canAct($request, $actorId)) {
            throw new WorkflowException('You are not one of the people who decide on "'.$step->name.'".');
        }

        $team = $this->teamFor($request, $step, $actorId);

        if ($decision === 'approve' && $this->alreadyApproved($request, $step, $actorId, $team)) {
            throw new WorkflowException($team === null ? 'You have already approved this step.' : 'Your team has already approved this step.');
        }

        DB::transaction(function () use ($request, $step, $actorId, $decision, $note, $team): void {
            RequestVote::query()->create(['purchase_request_id' => $request->id, 'step_id' => $step->id, 'user_id' => $actorId, 'team' => $team, 'decision' => $decision, 'note' => $note]);
            $this->log($request, 'voted', $step, $actorId, array_filter(['decision' => $decision, 'team' => $team, 'note' => $note]));

            if ($decision === 'changes' || $this->hasEnoughApprovals($request, $step)) {
                $this->apply($request, $this->runner->decide($this->definition, Flow::fromArray($request->flow ?? []), Position::at($step->id), $decision, $this->context($request, $actorId)));
            }
        });
    }

    /** Any one, all, a number, or one per team — the rule the designer chose on the step. */
    private function hasEnoughApprovals(PurchaseRequest $request, Step $step): bool
    {
        $votes = RequestVote::query()->where('purchase_request_id', $request->id)->where('step_id', $step->id)->where('decision', 'approve')->get();

        if ($step->type === ParallelApprovalStep::key()) {
            $teams = self::strings($step->settings['teams'] ?? []);

            return $teams !== [] && array_diff($teams, self::strings($votes->pluck('team')->all())) === [];
        }

        $approvers = array_values(array_unique(array_map(static fn (RequestVote $vote): int => $vote->user_id, $votes->all())));
        $count = $step->settings['count'] ?? 1;

        return match ($step->settings['rule'] ?? 'any') {
            'all' => array_diff($this->whoCanAct($request), $approvers) === [],
            'count' => count($approvers) >= max(1, is_numeric($count) ? (int) $count : 1),
            default => $approvers !== [],
        };
    }

    /** For a parallel review: the first of the actor's teams on this step that has not approved yet. */
    private function teamFor(PurchaseRequest $request, Step $step, int $actorId): ?string
    {
        if ($step->type !== ParallelApprovalStep::key()) {
            return null;
        }

        $mine = array_values(array_intersect(self::strings($step->settings['teams'] ?? []), $this->directory->keysOf($actorId)));
        $done = self::strings(RequestVote::query()->where('purchase_request_id', $request->id)->where('step_id', $step->id)->where('decision', 'approve')->pluck('team')->all());

        return array_values(array_diff($mine, $done))[0] ?? $mine[0] ?? null;
    }

    private function alreadyApproved(PurchaseRequest $request, Step $step, int $actorId, ?string $team): bool
    {
        $query = RequestVote::query()->where('purchase_request_id', $request->id)->where('step_id', $step->id)->where('decision', 'approve');

        return ($team === null ? $query->where('user_id', $actorId) : $query->where('team', $team))->exists();
    }

    /**
     * The definition keys a step asks: its approvers, or its teams.
     *
     * @return list<string>
     */
    private function keysOf(Step $step): array
    {
        return self::strings($step->type === ParallelApprovalStep::key() ? ($step->settings['teams'] ?? []) : ($step->settings['approvers'] ?? []));
    }

    /** Where the runner put the request becomes its status. */
    private function apply(PurchaseRequest $request, Position $position): void
    {
        $request->position = $position->toArray();
        $step = $this->stepOf($request);

        if ($position->isReturned()) {
            $request->forceFill(['status' => PurchaseRequest::CHANGES_REQUESTED, 'current_step' => null])->save();

            return;
        }

        if ($position->isFinished() || $step === null) {
            $request->forceFill(['status' => PurchaseRequest::APPROVED, 'current_step' => null])->save();

            return;
        }

        // A new round on this step: earlier decisions (before changes were asked for) no longer count.
        RequestVote::query()->where('purchase_request_id', $request->id)->where('step_id', $step->id)->delete();
        $request->forceFill(['status' => PurchaseRequest::IN_REVIEW, 'current_step' => $step->name, 'step_reached_at' => Carbon::now(), 'reminded_at' => null])->save();

        if ($this->whoCanAct($request) === []) {
            // Fail loudly, not silently: nobody holds the step's keys. An administrator assigns someone, then retries.
            $request->forceFill(['status' => PurchaseRequest::STUCK])->save();
            $this->log($request, 'stuck', $step, null, ['keys' => $this->keysOf($step)]);

            return;
        }

        $this->notifyApprovers($request, $step);
    }

    private function notifyApprovers(PurchaseRequest $request, Step $step, bool $reminder = false): void
    {
        Notification::send(ExampleUser::query()->whereIn('id', $this->whoCanAct($request))->get(), new StepWaitingForYou($request, $step->name, $reminder));
    }

    /**
     * The strings in a stored list (settings are plain JSON).
     *
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        return array_values(array_filter(is_array($value) ? $value : [], 'is_string'));
    }

    /** @return array<string, mixed> */
    private function context(PurchaseRequest $request, int $actorId): array
    {
        return [...$request->context(), 'actor_id' => $actorId];
    }

    /** @param  array<string, mixed>  $details */
    private function log(PurchaseRequest $request, string $event, ?Step $step, ?int $actorId, array $details = []): void
    {
        RequestHistory::query()->create([
            'purchase_request_id' => $request->id,
            'event' => $event,
            'step_id' => $step?->id,
            'step_name' => $step?->name,
            'actor_id' => $actorId,
            'details' => $details === [] ? null : $details,
            'created_at' => Carbon::now(),
        ]);
    }
}
