<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\RequestHistory;
use Banyan\FilamentFlowBuilder\Runtime\Events\FlowFinished;
use Banyan\FilamentFlowBuilder\Runtime\Events\FlowReturned;
use Banyan\FilamentFlowBuilder\Runtime\Events\OutcomeChosen;
use Banyan\FilamentFlowBuilder\Runtime\Events\StepReached;
use Banyan\FilamentFlowBuilder\Runtime\Events\StepSkipped;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Carbon;

/**
 * The audit log, from the runner's events: every step reached or skipped,
 * every outcome, every return and finish. The context carries which request
 * and who acted, so the runner itself needs to know nothing about either.
 */
class RecordHistory
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(StepReached::class, fn (StepReached $e) => $this->write($e->definition, $e->context, 'step_reached', $e->step->id, $e->step->name));
        $events->listen(StepSkipped::class, fn (StepSkipped $e) => $this->write($e->definition, $e->context, 'step_skipped', $e->step->id, $e->step->name, ['because' => 'its condition did not hold']));
        $events->listen(OutcomeChosen::class, fn (OutcomeChosen $e) => $this->write($e->definition, $e->context, 'outcome', $e->step->id, $e->step->name, ['outcome' => $e->outcome->key, 'label' => $e->outcome->label]));
        $events->listen(FlowReturned::class, fn (FlowReturned $e) => $this->write($e->definition, $e->context, 'returned', $e->from->id, $e->from->name));
        $events->listen(FlowFinished::class, fn (FlowFinished $e) => $this->write($e->definition, $e->context, 'finished'));
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $details
     */
    private function write(string $definition, array $context, string $event, ?string $stepId = null, ?string $stepName = null, array $details = []): void
    {
        // Other flows in your app have their own logs.
        if ($definition !== PurchaseFlow::key() || ! is_int($context['purchase_request_id'] ?? null)) {
            return;
        }

        RequestHistory::query()->create([
            'purchase_request_id' => $context['purchase_request_id'],
            'event' => $event,
            'step_id' => $stepId,
            'step_name' => $stepName,
            'actor_id' => is_int($context['actor_id'] ?? null) ? $context['actor_id'] : null,
            'details' => $details === [] ? null : $details,
            'created_at' => Carbon::now(),
        ]);
    }
}
