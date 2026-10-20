<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Runtime;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Runtime\Events\FlowFinished;
use Banyan\FilamentFlowBuilder\Runtime\Events\FlowReturned;
use Banyan\FilamentFlowBuilder\Runtime\Events\OutcomeChosen;
use Banyan\FilamentFlowBuilder\Runtime\Events\StepReached;
use Banyan\FilamentFlowBuilder\Runtime\Events\StepSkipped;
use Illuminate\Contracts\Events\Dispatcher;
use InvalidArgumentException;

/**
 * Moves an item through a flow. Stateless: the host keeps the Position and
 * the context (what it knows about the item) and calls start() once, then
 * decide() each time someone chooses an outcome. Every move is an event, so
 * notifications, audit and side effects stay in the host app.
 */
final readonly class FlowRunner
{
    public function __construct(private Dispatcher $events) {}

    /** @param array<string, mixed> $context */
    public function start(FlowDefinition $definition, Flow $flow, array $context = []): Position
    {
        return $this->advanceFrom(0, $definition, $flow, $context);
    }

    /** @param array<string, mixed> $context */
    public function decide(FlowDefinition $definition, Flow $flow, Position $at, string $outcomeKey, array $context = []): Position
    {
        $index = $at->stepId === null ? null : $flow->indexOf($at->stepId);

        if ($index === null || $at->state !== Position::AT_STEP) {
            throw new InvalidArgumentException('The item is not at a step of this flow.');
        }

        $step = $flow->steps[$index];
        $outcome = $this->outcome($definition, $step, $outcomeKey);
        $this->events->dispatch(new OutcomeChosen($definition::key(), $flow, $step, $outcome, $context));

        return match ($outcome->goesTo) {
            Outcome::START => $this->returnFrom($definition, $flow, $step, $context),
            Outcome::FINISH => $this->finish($definition, $flow, $context),
            default => $this->advanceFrom($index + 1, $definition, $flow, $context),
        };
    }

    /** @param array<string, mixed> $context */
    private function advanceFrom(int $index, FlowDefinition $definition, Flow $flow, array $context): Position
    {
        for ($i = $index; $i < count($flow->steps); $i++) {
            $step = $flow->steps[$i];

            if (! $this->applies($definition, $step, $context)) {
                $this->events->dispatch(new StepSkipped($definition::key(), $flow, $step, $context));

                continue;
            }

            $this->events->dispatch(new StepReached($definition::key(), $flow, $step, $context));

            return Position::at($step->id);
        }

        return $this->finish($definition, $flow, $context);
    }

    /** @param array<string, mixed> $context */
    private function applies(FlowDefinition $definition, Step $step, array $context): bool
    {
        if ($step->when === null) {
            return true;
        }

        $condition = $definition->conditionType($step->when->type);

        // An unknown condition never silently skips a step: the step applies.
        return $condition === null || $condition->passes($step->when->settings, $context, $definition);
    }

    private function outcome(FlowDefinition $definition, Step $step, string $key): Outcome
    {
        foreach ($definition->stepType($step->type)?->outcomes($step, $definition) ?? [] as $outcome) {
            if ($outcome->key === $key) {
                return $outcome;
            }
        }

        throw new InvalidArgumentException("The step [{$step->id}] has no outcome [{$key}].");
    }

    /** @param array<string, mixed> $context */
    private function finish(FlowDefinition $definition, Flow $flow, array $context): Position
    {
        $this->events->dispatch(new FlowFinished($definition::key(), $flow, $context));

        return Position::finished();
    }

    /** @param array<string, mixed> $context */
    private function returnFrom(FlowDefinition $definition, Flow $flow, Step $step, array $context): Position
    {
        $this->events->dispatch(new FlowReturned($definition::key(), $flow, $step, $context));

        return Position::returned();
    }
}
