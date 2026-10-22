<?php

declare(strict_types=1);

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\ApprovalFlow;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\ExampleUser;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\PurchaseRequest;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Notifications\StepWaitingForYou;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\ApproverDirectory;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\PurchaseRequestWorkflow;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\WorkflowException;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->loadLaravelMigrations();
    $this->artisan('migrate')->run();
    Notification::fake();
});

function flowBuilderPrUser(string $name, string ...$keys): ExampleUser
{
    $user = ExampleUser::query()->create(['name' => $name, 'email' => strtolower($name).'@example.test', 'password' => 'x']);

    foreach ($keys as $key) {
        app(ApproverDirectory::class)->assign($key, $user->id);
    }

    return $user;
}

/** Manager approves → Finance and IT at the same time → Director, only above HKD 10,000. */
function flowBuilderPrFlow(array $managerSettings = ['approvers' => ['manager'], 'rule' => 'any', 'count' => 1]): ApprovalFlow
{
    return ApprovalFlow::query()->create(['name' => 'Purchases', 'flow' => ['steps' => [
        ['id' => 'manager', 'type' => 'approval', 'name' => 'Line manager', 'settings' => $managerSettings, 'when' => null, 'reminder_days' => 2],
        ['id' => 'teams', 'type' => 'parallel-approval', 'name' => 'Finance and IT', 'settings' => ['teams' => ['finance', 'it']], 'when' => null],
        ['id' => 'director', 'type' => 'approval', 'name' => 'Director', 'settings' => ['approvers' => ['director'], 'rule' => 'any', 'count' => 1],
            'when' => ['type' => 'value', 'settings' => ['field' => 'amount', 'operator' => 'greater_than', 'value' => '10000']]],
    ]]]);
}

function flowBuilderPrRequest(ExampleUser $requester, ApprovalFlow $flow, int $amount = 25000): PurchaseRequest
{
    return PurchaseRequest::query()->create(['title' => 'New laptops', 'amount' => $amount, 'department' => 'IT', 'requester_id' => $requester->id, 'approval_flow_id' => $flow->id, 'status' => PurchaseRequest::DRAFT]);
}

function flowBuilderPrWorkflow(): PurchaseRequestWorkflow
{
    return app(PurchaseRequestWorkflow::class);
}

/** @return list<string> "event:step" lines of the audit log */
function flowBuilderPrLog(PurchaseRequest $request): array
{
    return $request->history()->get()->map(fn ($h): string => $h->event.($h->step_id === null ? '' : ':'.$h->step_id))->all();
}

it('takes a request through every step to approved, keeping status, notifications and a full audit log', function (): void {
    [$alice, $mia, $fin, $ivan, $dora] = [flowBuilderPrUser('Alice'), flowBuilderPrUser('Mia', 'manager'), flowBuilderPrUser('Fin', 'finance'), flowBuilderPrUser('Ivan', 'it'), flowBuilderPrUser('Dora', 'director')];
    $request = flowBuilderPrRequest($alice, flowBuilderPrFlow());
    $workflow = flowBuilderPrWorkflow();

    $workflow->submit($request, $alice->id);
    expect($request->fresh()->status)->toBe(PurchaseRequest::IN_REVIEW)
        ->and($request->fresh()->current_step)->toBe('Line manager')
        ->and($workflow->whoCanAct($request->fresh()))->toBe([$mia->id]);
    Notification::assertSentTo($mia, StepWaitingForYou::class);

    $workflow->approve($request->fresh(), $mia->id, 'Fine by me');
    expect($request->fresh()->current_step)->toBe('Finance and IT');

    // A parallel review waits for every team.
    $workflow->approve($request->fresh(), $fin->id);
    expect($request->fresh()->current_step)->toBe('Finance and IT');
    $workflow->approve($request->fresh(), $ivan->id);
    expect($request->fresh()->current_step)->toBe('Director');

    $workflow->approve($request->fresh(), $dora->id);

    expect($request->fresh()->status)->toBe(PurchaseRequest::APPROVED)
        ->and(flowBuilderPrLog($request))->toBe([
            'submitted', 'step_reached:manager',
            'voted:manager', 'outcome:manager', 'step_reached:teams',
            'voted:teams', 'voted:teams', 'outcome:teams', 'step_reached:director',
            'voted:director', 'outcome:director', 'finished',
        ])
        ->and($request->history()->where('event', 'voted')->first()?->actor_id)->toBe($mia->id)
        ->and($request->history()->where('event', 'voted')->first()?->details)->toBe(['decision' => 'approve', 'note' => 'Fine by me']);
});

it('skips a step whose condition does not hold, and says so in the log', function (): void {
    [$alice, $mia, $fin, $ivan] = [flowBuilderPrUser('Alice'), flowBuilderPrUser('Mia', 'manager'), flowBuilderPrUser('Fin', 'finance'), flowBuilderPrUser('Ivan', 'it')];
    $request = flowBuilderPrRequest($alice, flowBuilderPrFlow(), amount: 4000);

    flowBuilderPrWorkflow()->submit($request, $alice->id);
    flowBuilderPrWorkflow()->approve($request->fresh(), $mia->id);
    flowBuilderPrWorkflow()->approve($request->fresh(), $fin->id);
    flowBuilderPrWorkflow()->approve($request->fresh(), $ivan->id);

    expect($request->fresh()->status)->toBe(PurchaseRequest::APPROVED)
        ->and(flowBuilderPrLog($request))->toContain('step_skipped:director');
});

it('sends a request back on "changes", and starts again from the first step when it is resubmitted', function (): void {
    [$alice, $mia, $fin] = [flowBuilderPrUser('Alice'), flowBuilderPrUser('Mia', 'manager'), flowBuilderPrUser('Fin', 'finance'), flowBuilderPrUser('Ivan', 'it')];
    $request = flowBuilderPrRequest($alice, flowBuilderPrFlow());
    flowBuilderPrWorkflow()->submit($request, $alice->id);
    flowBuilderPrWorkflow()->approve($request->fresh(), $mia->id);

    flowBuilderPrWorkflow()->requestChanges($request->fresh(), $fin->id, 'Get a second quote.');

    expect($request->fresh()->status)->toBe(PurchaseRequest::CHANGES_REQUESTED)
        ->and(flowBuilderPrLog($request))->toContain('returned:teams')
        ->and(flowBuilderPrWorkflow()->canAct($request->fresh(), $fin->id))->toBeFalse();

    flowBuilderPrWorkflow()->submit($request->fresh(), $alice->id);

    // The new round asks the manager again: the earlier approval no longer counts.
    expect($request->fresh()->current_step)->toBe('Line manager')
        ->and($request->votes()->where('step_id', 'manager')->count())->toBe(0);
});

it('needs every manager when the step says "all", or the number it says', function (): void {
    [$alice, $mia, $max] = [flowBuilderPrUser('Alice'), flowBuilderPrUser('Mia', 'manager'), flowBuilderPrUser('Max', 'manager'), flowBuilderPrUser('Fin', 'finance'), flowBuilderPrUser('Ivan', 'it')];
    $request = flowBuilderPrRequest($alice, flowBuilderPrFlow(['approvers' => ['manager'], 'rule' => 'all', 'count' => 1]));
    flowBuilderPrWorkflow()->submit($request, $alice->id);

    flowBuilderPrWorkflow()->approve($request->fresh(), $mia->id);
    expect($request->fresh()->current_step)->toBe('Line manager');

    flowBuilderPrWorkflow()->approve($request->fresh(), $max->id);
    expect($request->fresh()->current_step)->toBe('Finance and IT');
});

it('marks a request stuck when nobody can act on its step, and lets it try again once someone is assigned', function (): void {
    [$alice, $fin] = [flowBuilderPrUser('Alice'), flowBuilderPrUser('Fin')];
    // Nobody holds "finance" yet.
    $request = flowBuilderPrRequest($alice, ApprovalFlow::query()->create(['name' => 'Finance only', 'flow' => ['steps' => [
        ['id' => 'finance', 'type' => 'approval', 'name' => 'Finance', 'settings' => ['approvers' => ['finance'], 'rule' => 'any', 'count' => 1], 'when' => null],
    ]]]));

    flowBuilderPrWorkflow()->submit($request, $alice->id);

    expect($request->fresh()->status)->toBe(PurchaseRequest::STUCK)
        ->and(flowBuilderPrLog($request))->toContain('stuck:finance')
        ->and(fn () => flowBuilderPrWorkflow()->approve($request->fresh(), $fin->id))->toThrow(WorkflowException::class, 'not waiting for a decision');

    app(ApproverDirectory::class)->assign('finance', $fin->id);
    flowBuilderPrWorkflow()->retry($request->fresh(), $alice->id);

    expect($request->fresh()->status)->toBe(PurchaseRequest::IN_REVIEW)
        ->and(flowBuilderPrWorkflow()->whoCanAct($request->fresh()))->toBe([$fin->id])
        ->and(flowBuilderPrLog($request))->toContain('retried:finance');
});

it('keeps requests on the flow they were submitted with when the flow is changed later', function (): void {
    [$alice, $mia] = [flowBuilderPrUser('Alice'), flowBuilderPrUser('Mia', 'manager')];
    $flow = flowBuilderPrFlow();
    $request = flowBuilderPrRequest($alice, $flow);
    flowBuilderPrWorkflow()->submit($request, $alice->id);

    $flow->update(['flow' => ['steps' => []]]);

    expect(flowBuilderPrWorkflow()->stepOf($request->fresh())?->id)->toBe('manager')
        ->and(flowBuilderPrWorkflow()->canAct($request->fresh(), $mia->id))->toBeTrue();
});

it('refuses what people may not do, in words that can be shown to them', function (): void {
    [$alice, $mia, $bob] = [flowBuilderPrUser('Alice', 'manager'), flowBuilderPrUser('Mia', 'manager'), flowBuilderPrUser('Bob')];
    $broken = ApprovalFlow::query()->create(['name' => 'Broken', 'flow' => ['steps' => [
        ['id' => 'x', 'type' => 'approval', 'name' => 'Nobody chosen', 'settings' => ['approvers' => [], 'rule' => 'any', 'count' => 1], 'when' => null],
    ]]]);

    expect(fn () => flowBuilderPrWorkflow()->submit(flowBuilderPrRequest($alice, $broken), $alice->id))->toThrow(WorkflowException::class, 'has problems');

    $request = flowBuilderPrRequest($alice, flowBuilderPrFlow());
    flowBuilderPrWorkflow()->submit($request, $alice->id);

    expect(fn () => flowBuilderPrWorkflow()->submit($request->fresh(), $alice->id))->toThrow(WorkflowException::class, 'Only a draft')
        // Alice is a manager too, but nobody approves their own request.
        ->and(fn () => flowBuilderPrWorkflow()->approve($request->fresh(), $alice->id))->toThrow(WorkflowException::class, 'not one of the people')
        ->and(fn () => flowBuilderPrWorkflow()->approve($request->fresh(), $bob->id))->toThrow(WorkflowException::class, 'not one of the people')
        ->and(fn () => flowBuilderPrWorkflow()->requestChanges($request->fresh(), $mia->id, ' '))->toThrow(WorkflowException::class, 'what needs to change')
        ->and(fn () => flowBuilderPrWorkflow()->retry($request->fresh(), $mia->id))->toThrow(WorkflowException::class, 'stuck');
});

it('reminds the people of a step once it waits longer than its "Remind after" days', function (): void {
    [$alice, $mia] = [flowBuilderPrUser('Alice'), flowBuilderPrUser('Mia', 'manager')];
    $request = flowBuilderPrRequest($alice, flowBuilderPrFlow());
    flowBuilderPrWorkflow()->submit($request, $alice->id);
    Notification::fake();

    expect(flowBuilderPrWorkflow()->remindOverdue())->toBe(0);

    $this->travel(3)->days();

    expect(flowBuilderPrWorkflow()->remindOverdue())->toBe(1)
        ->and(flowBuilderPrWorkflow()->remindOverdue())->toBe(0)
        ->and(flowBuilderPrLog($request))->toContain('reminded:manager');
    Notification::assertSentTo($mia, StepWaitingForYou::class, fn (StepWaitingForYou $n): bool => $n->reminder);

    $this->artisan('purchase-requests:remind')->expectsOutputToContain('0 request(s) reminded.')->assertSuccessful();
});
