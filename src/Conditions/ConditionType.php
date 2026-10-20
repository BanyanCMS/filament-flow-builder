<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder\Conditions;

use Banyan\FlowBuilder\Definitions\FlowDefinition;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;

/**
 * A kind of "only when …" a step can carry ("Amount is over …", "Language
 * is …"). Registered in code; evaluated by the runner against the context
 * the host passes in. When it does not hold, the step is skipped.
 */
interface ConditionType
{
    public static function key(): string;

    public function label(): string;

    /** @return list<Component|Field> bound to the condition's settings */
    public function schema(FlowDefinition $definition): array;

    /** @param array<string, mixed> $settings the sentence on the card, e.g. "Amount is over 10,000" */
    public function describe(array $settings, FlowDefinition $definition): string;

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $context  what the host knows about the item moving through the flow
     */
    public function passes(array $settings, array $context, FlowDefinition $definition): bool;

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    public function validate(array $settings, FlowDefinition $definition): array;
}
