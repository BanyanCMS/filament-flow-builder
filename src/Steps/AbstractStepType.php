<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder\Steps;

use Banyan\FlowBuilder\Data\Outcome;
use Banyan\FlowBuilder\Data\Step;
use Banyan\FlowBuilder\Definitions\FlowDefinition;

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
}
