<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Validation;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;

/**
 * What is wrong with a flow, by step id ("flow" for the flow as a whole),
 * in the person's words. The builder shows these on the cards; the form
 * field refuses to save while any remain.
 */
final class FlowValidator
{
    /** @return array<string, list<string>> */
    public function problems(Flow $flow, FlowDefinition $definition): array
    {
        $problems = [];

        if (count($flow->steps) > $definition->maxSteps()) {
            $problems['flow'][] = (string) __('flow-builder::flow.too_many_steps', ['max' => $definition->maxSteps()]);
        }

        $start = $definition->validateSettings($flow->settings);

        if ($start !== []) {
            $problems['start'] = $start;
        }

        foreach ($flow->steps as $step) {
            $type = $definition->stepType($step->type);
            $found = [];

            if ($type === null) {
                $found[] = (string) __('flow-builder::flow.unknown_step_type', ['type' => $step->type]);
            } else {
                if (trim($step->name) === '') {
                    $found[] = (string) __('flow-builder::flow.no_name');
                }

                $found = [...$found, ...$type->validate($step, $definition)];
            }

            if ($step->when !== null) {
                $condition = $definition->conditionType($step->when->type);
                $found = $condition === null
                    ? [...$found, (string) __('flow-builder::flow.unknown_condition_type', ['type' => $step->when->type])]
                    : [...$found, ...$condition->validate($step->when->settings, $definition)];
            }

            if ($found !== []) {
                $problems[$step->id] = $found;
            }
        }

        return $problems;
    }

    public function passes(Flow $flow, FlowDefinition $definition): bool
    {
        return $this->problems($flow, $definition) === [];
    }
}
