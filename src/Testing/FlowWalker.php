<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Testing;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;

/**
 * The default "Test it as…": walks the flow as it is on screen (saved or
 * not) with the context the tester typed, taking each step's first way
 * forward, and says what happens at every step. Nothing is dispatched or
 * stored. A definition can replace it with its own (FlowDefinition::test()).
 */
final class FlowWalker
{
    /** @param array<string, mixed> $context */
    public function walk(FlowDefinition $definition, Flow $flow, array $context): FlowTestResult
    {
        $lines = [];

        foreach ($flow->steps as $step) {
            $name = $step->name !== '' ? $step->name : (string) __('flow-builder::flow.unnamed');
            $condition = $step->when === null ? null : $definition->conditionType($step->when->type);
            $settings = $step->when === null ? [] : $step->when->settings;

            if ($condition !== null && ! $condition->passes($settings, $context, $definition)) {
                $lines[] = (string) __('flow-builder::flow.test.skipped', ['step' => $name, 'why' => $condition->describe($settings, $definition)]);

                continue;
            }

            $forward = null;

            foreach ($definition->stepType($step->type)?->outcomes($step, $definition) ?? [] as $outcome) {
                if (in_array($outcome->goesTo, [Outcome::NEXT, Outcome::FINISH], true)) {
                    $forward = $outcome;

                    break;
                }
            }

            if ($forward === null) {
                return new FlowTestResult([...$lines, (string) __('flow-builder::flow.test.stuck_at', ['step' => $name])], (string) __('flow-builder::flow.test.outcome_stuck', ['step' => $name]));
            }

            $lines[] = (string) __('flow-builder::flow.test.passed', ['step' => $name, 'outcome' => $forward->label]);

            if ($forward->goesTo === Outcome::FINISH) {
                break;
            }
        }

        return new FlowTestResult($lines, (string) __('flow-builder::flow.test.outcome_finished', ['finish' => $definition->finishLabel()]));
    }
}
