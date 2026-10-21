<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Steps;

use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Filament\Forms\Components\Select;

/**
 * The built-in parallel review: two or more teams review at the same time,
 * and the step is done when each team has approved. Teams come from the
 * definition (FlowDefinition::teams()); counting approvals per team is the
 * host's job, the builder records who must take part.
 */
class ParallelApprovalStep extends AbstractStepType
{
    public static function key(): string
    {
        return 'parallel-approval';
    }

    public function label(): string
    {
        return (string) __('flow-builder::flow.parallel.label');
    }

    public function description(): string
    {
        return (string) __('flow-builder::flow.parallel.description');
    }

    public function icon(): string
    {
        return 'heroicon-m-squares-2x2';
    }

    public function color(): string
    {
        return 'info';
    }

    public function defaults(FlowDefinition $definition): array
    {
        // Starts with the first two teams, so the step is valid the moment it is added.
        return ['teams' => array_slice(array_keys($definition->teams()), 0, 2)];
    }

    public function schema(FlowDefinition $definition): array
    {
        return [
            Select::make('teams')
                ->label(__('flow-builder::flow.parallel.teams'))
                ->helperText(__('flow-builder::flow.parallel.teams_help'))
                ->options($definition->teams())
                ->multiple()
                ->searchable()
                ->live(),
        ];
    }

    public function summary(Step $step, FlowDefinition $definition): array
    {
        $teams = $this->teamNames($step, $definition);

        return $teams === [] ? [] : [...$teams, (string) __('flow-builder::flow.parallel.each')];
    }

    public function outcomes(Step $step, FlowDefinition $definition): array
    {
        return [
            new Outcome('approve', (string) __('flow-builder::flow.parallel.all_approved'), Outcome::NEXT, 'success'),
            new Outcome('changes', (string) __('flow-builder::flow.approval.changes'), Outcome::START, 'warning'),
        ];
    }

    public function validate(Step $step, FlowDefinition $definition): array
    {
        return count($this->teamNames($step, $definition)) < 2 ? [(string) __('flow-builder::flow.parallel.two_teams')] : [];
    }

    public function remindable(): bool
    {
        return true;
    }

    /** @return list<string> */
    private function teamNames(Step $step, FlowDefinition $definition): array
    {
        $options = $definition->teams();
        $names = [];

        foreach (is_array($step->settings['teams'] ?? null) ? $step->settings['teams'] : [] as $id) {
            if ((is_string($id) || is_int($id)) && isset($options[(string) $id])) {
                $names[] = $options[(string) $id];
            }
        }

        return $names;
    }
}
