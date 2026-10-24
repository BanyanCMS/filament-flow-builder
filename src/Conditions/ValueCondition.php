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
    public const string IS = 'is';

    public const string IS_NOT = 'is_not';

    public const string GREATER_THAN = 'greater_than';

    public const string LESS_THAN = 'less_than';

    public const string CONTAINS = 'contains';

    public const array OPERATORS = [self::IS, self::IS_NOT, self::GREATER_THAN, self::LESS_THAN, self::CONTAINS];

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
        $operator = in_array($settings['operator'] ?? null, self::OPERATORS, true) ? $settings['operator'] : self::IS;
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

        return match ($settings['operator'] ?? self::IS) {
            self::IS => self::text($actual) === self::text($expected),
            self::IS_NOT => self::text($actual) !== self::text($expected),
            self::GREATER_THAN => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            self::LESS_THAN => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            self::CONTAINS => is_array($actual)
                ? in_array(self::text($expected), array_map(self::text(...), $actual), true)
                : self::text($expected) !== '' && str_contains(mb_strtolower(self::text($actual)), mb_strtolower(self::text($expected))),
            default => false,
        };
    }

    public function defaults(FlowDefinition $definition): array
    {
        $field = array_key_first($definition->contextFields());

        return ['field' => $field, 'operator' => self::IS, 'value' => ''];
    }

    public function validate(array $settings, FlowDefinition $definition): array
    {
        $problems = [];

        if (! is_string($settings['field'] ?? null) || ! array_key_exists($settings['field'], $definition->contextFields())) {
            $problems[] = (string) __('flow-builder::flow.condition.no_field');
        }

        if (in_array($settings['operator'] ?? self::IS, [self::GREATER_THAN, self::LESS_THAN], true) && ! is_numeric($settings['value'] ?? null)) {
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
