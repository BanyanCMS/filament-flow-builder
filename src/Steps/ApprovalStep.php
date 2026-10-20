<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Steps;

use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * The built-in approval: chosen approvers, and how many of them must agree.
 * Approve goes on; Request changes goes back to the start. Who the approvers
 * can be comes from the definition (FlowDefinition::approvers()).
 */
class ApprovalStep extends AbstractStepType
{
    public static function key(): string
    {
        return 'approval';
    }

    public function label(): string
    {
        return (string) __('flow-builder::flow.approval.label');
    }

    public function description(): string
    {
        return (string) __('flow-builder::flow.approval.description');
    }

    public function defaults(FlowDefinition $definition): array
    {
        return ['approvers' => [], 'rule' => 'any', 'count' => 1];
    }

    public function schema(FlowDefinition $definition): array
    {
        return [
            Select::make('approvers')
                ->label(__('flow-builder::flow.approval.approvers'))
                ->options($definition->approvers())
                ->multiple()
                ->searchable()
                ->live(),
            Select::make('rule')
                ->label(__('flow-builder::flow.approval.rule'))
                ->options([
                    'any' => __('flow-builder::flow.approval.rule_any'),
                    'all' => __('flow-builder::flow.approval.rule_all'),
                    'count' => __('flow-builder::flow.approval.rule_count'),
                ])
                ->native(false)
                ->live(),
            TextInput::make('count')
                ->label(__('flow-builder::flow.approval.count'))
                ->numeric()
                ->minValue(1)
                ->visible(fn (callable $get): bool => $get('rule') === 'count')
                ->live(onBlur: true),
        ];
    }

    public function summary(Step $step, FlowDefinition $definition): array
    {
        $names = $this->approverNames($step, $definition);

        if ($names === []) {
            return [];
        }

        $rule = $step->settings['rule'] ?? 'any';
        $count = max(1, self::number($step->settings['count'] ?? 1));
        $who = implode(', ', $names);

        return [match ($rule) {
            'all' => (string) __('flow-builder::flow.approval.summary_all', ['who' => $who]),
            'count' => (string) __('flow-builder::flow.approval.summary_count', ['count' => $count, 'who' => $who]),
            default => (string) __('flow-builder::flow.approval.summary_any', ['who' => $who]),
        }];
    }

    public function outcomes(Step $step, FlowDefinition $definition): array
    {
        return [
            new Outcome('approve', (string) __('flow-builder::flow.approval.approve'), Outcome::NEXT, 'success'),
            new Outcome('changes', (string) __('flow-builder::flow.approval.changes'), Outcome::START, 'warning'),
        ];
    }

    public function validate(Step $step, FlowDefinition $definition): array
    {
        $problems = [];
        $names = $this->approverNames($step, $definition);

        if ($names === []) {
            $problems[] = (string) __('flow-builder::flow.approval.no_approvers');
        }

        if (($step->settings['rule'] ?? 'any') === 'count' && self::number($step->settings['count'] ?? 0) > count($names)) {
            $problems[] = (string) __('flow-builder::flow.approval.count_too_high', ['count' => count($names)]);
        }

        return $problems;
    }

    private static function number(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /** @return list<string> */
    private function approverNames(Step $step, FlowDefinition $definition): array
    {
        $options = $definition->approvers();
        $names = [];

        foreach (is_array($step->settings['approvers'] ?? null) ? $step->settings['approvers'] : [] as $id) {
            if ((is_string($id) || is_int($id)) && isset($options[(string) $id])) {
                $names[] = $options[(string) $id];
            }
        }

        return $names;
    }
}
