<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Canvas;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;

/**
 * Turns a flow into what the canvas draws: one card per step, and the Start
 * card. It reads the flow and the problems it is given and changes nothing.
 *
 * @internal used by InteractsWithFlowBuilder
 */
final readonly class CanvasBuilder
{
    /** The Start card's id, in the problems lists too. */
    public const string START = 'start';

    /**
     * @param  array<string, list<string>>  $problems  what the validator found, by step id
     * @param  array<string, list<string>>  $hostProblems  what the host found (e.g. on save), by step id
     */
    public function __construct(
        private Flow $flow,
        private FlowDefinition $definition,
        private array $problems,
        private array $hostProblems = [],
    ) {}

    /**
     * The step cards, in running order.
     *
     * @return list<array{id: string, number: int, kind: string, name: string, icon: string, color: string, chips: list<string>, outcomes: list<array{label: string, tone: string}>, condition: string|null, problems: list<string>}>
     */
    public function cards(): array
    {
        $definition = $this->definition;
        $last = count($this->flow->steps) - 1;
        $cards = [];

        foreach ($this->flow->steps as $index => $step) {
            $type = $definition->stepType($step->type);
            $condition = $step->when === null ? null : $definition->conditionType($step->when->type);

            $chips = $type?->summary($step, $definition) ?? [];

            if ($step->reminderDays !== null && $type?->remindable() === true) {
                $chips[] = (string) trans_choice('flow-builder::flow.reminder_chip', $step->reminderDays, ['days' => $step->reminderDays]);
            }

            $cards[] = [
                'id' => $step->id,
                'number' => $index + 1,
                'kind' => $type?->cardKind($step, $definition) ?? $step->type,
                'name' => $step->name,
                'icon' => $type?->cardIcon($step, $definition) ?? 'heroicon-m-question-mark-circle',
                'color' => $type?->cardColor($step, $definition) ?? 'gray',
                'chips' => $chips,
                'outcomes' => array_map(fn (Outcome $outcome): array => [
                    'label' => $this->outcomeLabel($outcome, $index === $last),
                    'tone' => $outcome->tone,
                ], $type?->outcomes($step, $definition) ?? []),
                'condition' => $condition?->describe($step->when->settings ?? [], $definition),
                'problems' => [...($this->problems[$step->id] ?? []), ...($this->hostProblems[$step->id] ?? [])],
            ];
        }

        return $cards;
    }

    /**
     * The Start card: the flow's own settings when the definition has any.
     *
     * @return array{selectable: bool, chips: list<string>, problems: list<string>}
     */
    public function start(): array
    {
        return [
            'selectable' => $this->definition->settingsSchema() !== [],
            'chips' => $this->definition->startSummary($this->flow->settings),
            'problems' => [...($this->problems[self::START] ?? []), ...($this->hostProblems[self::START] ?? [])],
        ];
    }

    private function outcomeLabel(Outcome $outcome, bool $last): string
    {
        $where = match (true) {
            $outcome->goesTo === Outcome::START => $this->definition->returnLabel(),
            $outcome->goesTo === Outcome::FINISH, $last => $this->definition->finishLabel(),
            default => __('flow-builder::flow.goes_next'),
        };

        return $outcome->label.' → '.$where;
    }
}
