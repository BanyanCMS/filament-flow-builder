<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Conditions;

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * The built-in condition: compare one value the host provides
 * (FlowDefinition::contextFields()) with what the designer typed.
 */
class ValueCondition implements ConditionType
{
    public const array OPERATORS = ['is', 'is_not', 'greater_than', 'less_than', 'contains'];

    public static function key(): string
    {
        return 'value';
    }

    public function label(): string
    {
        return (string) __('flow-builder::flow.condition.value_label');
    }

    public function schema(FlowDefinition $definition): array
    {
        return [
            Select::make('field')
                ->label(__('flow-builder::flow.condition.field'))
                ->options($definition->contextFields())
                ->native(false)
                ->live(),
            Select::make('operator')
                ->label(__('flow-builder::flow.condition.operator'))
                ->options(array_combine(self::OPERATORS, array_map(static fn (string $op): string => (string) __('flow-builder::flow.condition.operators.'.$op), self::OPERATORS)))
                ->native(false)
                ->live(),
            TextInput::make('value')
                ->label(__('flow-builder::flow.condition.value'))
                ->live(onBlur: true),
        ];
    }

    public function describe(array $settings, FlowDefinition $definition): string
    {
        $field = is_string($settings['field'] ?? null) ? ($definition->contextFields()[$settings['field']] ?? $settings['field']) : '…';
        $operator = in_array($settings['operator'] ?? null, self::OPERATORS, true) ? $settings['operator'] : 'is';
        $value = is_scalar($settings['value'] ?? null) ? (string) $settings['value'] : '…';

        return (string) __('flow-builder::flow.condition.sentence', [
            'field' => $field,
            'operator' => __('flow-builder::flow.condition.operators.'.$operator),
            'value' => $value,
        ]);
    }

    public function passes(array $settings, array $context, FlowDefinition $definition): bool
    {
        $field = is_string($settings['field'] ?? null) ? $settings['field'] : null;

        if ($field === null || ! array_key_exists($field, $context)) {
            return false;
        }

        $actual = $context[$field];
        $expected = $settings['value'] ?? null;

        return match ($settings['operator'] ?? 'is') {
            'is' => self::text($actual) === self::text($expected),
            'is_not' => self::text($actual) !== self::text($expected),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'contains' => is_array($actual)
                ? in_array(self::text($expected), array_map(self::text(...), $actual), true)
                : self::text($expected) !== '' && str_contains(mb_strtolower(self::text($actual)), mb_strtolower(self::text($expected))),
            default => false,
        };
    }

    public function validate(array $settings, FlowDefinition $definition): array
    {
        $problems = [];

        if (! is_string($settings['field'] ?? null) || ! array_key_exists($settings['field'], $definition->contextFields())) {
            $problems[] = (string) __('flow-builder::flow.condition.no_field');
        }

        if (in_array($settings['operator'] ?? 'is', ['greater_than', 'less_than'], true) && ! is_numeric($settings['value'] ?? null)) {
            $problems[] = (string) __('flow-builder::flow.condition.not_a_number');
        }

        return $problems;
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value) => trim((string) $value),
            default => '',
        };
    }
}
