<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Steps;

use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Data\StepPreset;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;

/**
 * Sensible defaults for a step type: one "Done" outcome, no settings, no
 * checks. Override what your step needs.
 */
abstract class AbstractStepType implements StepType
{
    public function description(): string
    {
        return '';
    }

    public function icon(): string
    {
        return 'heroicon-m-check-badge';
    }

    public function color(): string
    {
        return 'primary';
    }

    public function defaults(FlowDefinition $definition): array
    {
        return [];
    }

    public function schema(FlowDefinition $definition): array
    {
        return [];
    }

    public function summary(Step $step, FlowDefinition $definition): array
    {
        return [];
    }

    public function outcomes(Step $step, FlowDefinition $definition): array
    {
        return [new Outcome('done', (string) __('flow-builder::flow.outcome_done'), Outcome::NEXT, 'success')];
    }

    public function validate(Step $step, FlowDefinition $definition): array
    {
        return [];
    }

    public function presets(FlowDefinition $definition): array
    {
        return [new StepPreset('default', $this->label(), $this->description(), $this->icon(), $this->label(), $this->defaults($definition))];
    }

    public function cardKind(Step $step, FlowDefinition $definition): string
    {
        return $this->label();
    }

    public function cardIcon(Step $step, FlowDefinition $definition): string
    {
        return $this->icon();
    }

    public function cardColor(Step $step, FlowDefinition $definition): string
    {
        return $this->color();
    }

    public function remindable(): bool
    {
        return false;
    }

    /**
     * The labels of the chosen ids that exist in $options, in the order chosen.
     *
     * @param  array<array-key, string>  $options  id => label
     * @return list<string>
     */
    protected function chosenLabels(array $options, mixed $ids): array
    {
        $labels = [];

        foreach (is_array($ids) ? $ids : [] as $id) {
            if ((is_string($id) || is_int($id)) && isset($options[(string) $id])) {
                $labels[] = $options[(string) $id];
            }
        }

        return $labels;
    }
}
