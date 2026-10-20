<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Steps;

use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * A kind of step people can add to a flow ("Approval", "Legal check", …).
 * Registered in code on a FlowDefinition; the builder shows it in the "+"
 * menu, draws its card and builds its settings panel from schema().
 */
interface StepType
{
    /** Stored on each step; never change it once flows use it. */
    public static function key(): string;

    public function label(): string;

    /** One line under the label in the "+" menu. */
    public function description(): string;

    public function icon(): string;

    /** @return 'primary'|'warning'|'success'|'danger'|'info'|'gray' */
    public function color(): string;

    /** @return array<string, mixed> settings a new step starts with */
    public function defaults(FlowDefinition $definition): array;

    /**
     * Filament components for the step's settings, bound to its settings.
     *
     * @return list<Component|Field>
     */
    public function schema(FlowDefinition $definition): array;

    /** @return list<string> short facts shown as chips on the card */
    public function summary(Step $step, FlowDefinition $definition): array;

    /** @return list<Outcome> */
    public function outcomes(Step $step, FlowDefinition $definition): array;

    /** @return list<string> what is wrong with this step, in the person's words */
    public function validate(Step $step, FlowDefinition $definition): array;
}
