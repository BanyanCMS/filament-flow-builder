<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Concerns;

use Banyan\FilamentFlowBuilder\Actions\TestFlowAction;
use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\Validation\FlowValidator;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The flow builder on any Livewire component that has Filament schemas and
 * actions: the stand-alone FlowBuilder component uses it, and so can a page
 * that needs the canvas next to its own save, settings and checks.
 *
 * The host declares `public array $flow` (the flow as plain data) and says
 * which definition it edits, whether it is read-only and whether to show the
 * built-in "Test it as…". It renders the canvas with
 * `@include('flow-builder::canvas')` and the action
 * modals with `<x-filament-actions::modals />` (a Filament page already does).
 */
trait InteractsWithFlowBuilder
{
    /** "Only when" with no condition. */
    public const string FLOW_ALWAYS = '__always';

    /** The Start card's selection id. */
    public const string FLOW_START = 'start';

    public ?string $selected = null;

    /** @var array<string, mixed> the selected step, as the panel edits it */
    public array $stepData = [];

    /** @var array<string, mixed> the flow's own settings, as the Start panel edits them */
    public array $flowSettings = [];

    /** @var array<string, list<string>> step id (or "start") => problems the host found, e.g. on save */
    public array $flowProblems = [];

    abstract public function flowDefinitionKey(): string;

    public function flowIsReadOnly(): bool
    {
        return false;
    }

    public function flowIsTestable(): bool
    {
        return true;
    }

    /** Called after every change to the flow (e.g. to mark the page unsaved). */
    protected function flowChanged(): void {}

    /** Called when "+" is used on a flow that already has the most steps it may. */
    protected function flowFull(int $max): void
    {
        Notification::make()->warning()->title(__('flow-builder::flow.too_many_steps', ['max' => $max]))->send();
    }

    /**
     * What the canvas view needs.
     *
     * @return array{cards: list<array<string, mixed>>, start: array{selectable: bool, chips: list<string>, problems: list<string>}, flowDefinition: FlowDefinition}
     */
    public function flowCanvas(): array
    {
        return ['cards' => $this->flowCards(), 'start' => $this->flowStartCard(), 'flowDefinition' => $this->flowDefinition()];
    }

    public function select(?string $id): void
    {
        // Errors under the panel's fields belong to what was selected before.
        $panelErrors = array_values(array_filter(
            $this->getErrorBag()->keys(),
            static fn (string $key): bool => str_starts_with($key, 'stepData.') || str_starts_with($key, 'flowSettings.'),
        ));

        if ($panelErrors !== []) {
            $this->resetErrorBag($panelErrors);
        }

        if ($id === self::FLOW_START) {
            $this->selectFlowStart();

            return;
        }

        $step = $id === null ? null : $this->currentFlow()->find($id);
        $this->selected = $step?->id;
        $this->stepData = [];
        unset($this->cachedSchemas['stepForm']);

        if ($step !== null) {
            $this->fillFlowPanel([
                'name' => $step->name,
                'settings' => $step->settings,
                'when_type' => $step->when->type ?? self::FLOW_ALWAYS,
                'when_settings' => $step->when->settings ?? [],
                'reminder_days' => $step->reminderDays,
            ]);
        }
    }

    /** Adds a step at a "+" (0 is right after Start), from one of its type's presets. */
    public function insertStep(int $position, string $type, string $preset = 'default'): void
    {
        $definition = $this->flowDefinition();
        $stepType = $definition->stepType($type);
        $flow = $this->currentFlow();
        $chosen = null;

        foreach ($stepType?->presets($definition) ?? [] as $candidate) {
            $chosen = $candidate->key === $preset ? $candidate : $chosen;
        }

        if ($this->flowIsReadOnly() || $chosen === null) {
            return;
        }

        if (count($flow->steps) >= $definition->maxSteps()) {
            $this->flowFull($definition->maxSteps());

            return;
        }

        $step = new Step($definition->newStepId(), $type, $chosen->name, $chosen->settings, $chosen->when, $chosen->reminderDays);
        $this->storeFlow($flow->insert($position, $step));
        $this->select($step->id);
    }

    public function removeStep(string $id): void
    {
        if ($this->flowIsReadOnly()) {
            return;
        }

        $this->storeFlow($this->currentFlow()->remove($id));

        if ($this->selected === $id) {
            $this->select($this->flowDefinition()->settingsSchema() === [] ? null : self::FLOW_START);
        }
    }

    /** Drops a step on a "+": it then sits before the step now at $position. */
    public function moveStep(string $id, int $position): void
    {
        if (! $this->flowIsReadOnly()) {
            $this->storeFlow($this->currentFlow()->move($id, $position));
        }
    }

    public function moveStepUp(string $id): void
    {
        $index = $this->currentFlow()->indexOf($id);

        if ($index !== null && $index > 0) {
            $this->moveStep($id, $index - 1);
        }
    }

    public function moveStepDown(string $id): void
    {
        $index = $this->currentFlow()->indexOf($id);

        if ($index !== null) {
            $this->moveStep($id, $index + 2);
        }
    }

    /** The panel edits the selected step; every change goes straight back into the flow. */
    public function updatedStepData(mixed $value = null, ?string $key = null): void
    {
        // A different condition brings different fields: give each of them its key.
        if ($key === 'when_type' && ! $this->flowIsReadOnly()) {
            $this->fillFlowPanel([...$this->stepData, 'when_settings' => []]);
        }

        $flow = $this->currentFlow();
        $step = $this->selected === null ? null : $flow->find($this->selected);

        if ($this->flowIsReadOnly() || $step === null) {
            return;
        }

        $whenType = is_string($this->stepData['when_type'] ?? null) ? $this->stepData['when_type'] : self::FLOW_ALWAYS;

        $this->storeFlow($flow->replace($step->with([
            'name' => is_string($this->stepData['name'] ?? null) ? $this->stepData['name'] : '',
            'settings' => $this->stepData['settings'] ?? [],
            'when' => in_array($whenType, ['', self::FLOW_ALWAYS], true) ? null : ['type' => $whenType, 'settings' => $this->stepData['when_settings'] ?? []],
            'reminder_days' => $this->stepData['reminder_days'] ?? null,
        ])));
    }

    /** The Start panel edits the flow's own settings. */
    public function updatedFlowSettings(): void
    {
        if (! $this->flowIsReadOnly()) {
            $this->storeFlow($this->currentFlow()->withSettings($this->flowSettings));
        }
    }

    public function settingsForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('flowSettings')
            ->disabled($this->flowIsReadOnly())
            ->components($this->selected === self::FLOW_START ? $this->flowDefinition()->settingsSchema() : []);
    }

    public function testAction(): Action
    {
        return TestFlowAction::make('test')->definition($this->flowDefinitionKey())->flowUsing(fn (): Flow => $this->currentFlow());
    }

    public function stepForm(Schema $schema): Schema
    {
        $definition = $this->flowDefinition();
        $step = $this->selected === null ? null : $this->currentFlow()->find($this->selected);
        $type = $step === null ? null : $definition->stepType($step->type);

        if ($step === null || $type === null) {
            return $schema->statePath('stepData')->components([]);
        }

        $conditions = $definition->conditionTypes();
        $components = [
            TextInput::make('name')
                ->label(__('flow-builder::flow.panel.name'))
                ->maxLength(255)
                ->live(onBlur: true),
            Group::make($type->schema($definition))->statePath('settings'),
        ];

        if ($type->remindable()) {
            $components[] = TextInput::make('reminder_days')
                ->label(__('flow-builder::flow.panel.reminder'))
                ->helperText(__('flow-builder::flow.panel.reminder_help'))
                ->numeric()
                ->minValue(1)
                ->maxValue(365)
                ->suffix(__('flow-builder::flow.panel.days'))
                ->live(onBlur: true);
        }

        if ($conditions !== []) {
            $components[] = Select::make('when_type')
                ->label(__('flow-builder::flow.panel.when'))
                ->options([self::FLOW_ALWAYS => __('flow-builder::flow.panel.always'), ...array_map(static fn ($c): string => $c->label(), $conditions)])
                ->selectablePlaceholder(false)
                ->native(false)
                ->live();

            foreach ($conditions as $key => $condition) {
                $components[] = Group::make($condition->schema($definition))
                    ->statePath('when_settings')
                    ->visible(fn (Get $get): bool => $get('when_type') === $key);
            }
        }

        return $schema
            ->statePath('stepData')
            ->disabled($this->flowIsReadOnly())
            ->components($components);
    }

    /**
     * What the canvas draws, in running order.
     *
     * @return list<array{id: string, number: int, kind: string, name: string, icon: string, color: string, chips: list<string>, outcomes: list<array{label: string, tone: string}>, condition: string|null, problems: list<string>}>
     */
    public function flowCards(): array
    {
        $definition = $this->flowDefinition();
        $flow = $this->currentFlow();
        $problems = app(FlowValidator::class)->problems($flow, $definition);
        $last = count($flow->steps) - 1;
        $cards = [];

        foreach ($flow->steps as $index => $step) {
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
                    'label' => $this->flowOutcomeLabel($outcome, $index === $last, $definition),
                    'tone' => $outcome->tone,
                ], $type?->outcomes($step, $definition) ?? []),
                'condition' => $condition?->describe($step->when->settings ?? [], $definition),
                'problems' => [...($problems[$step->id] ?? []), ...($this->flowProblems[$step->id] ?? [])],
            ];
        }

        return $cards;
    }

    /**
     * The Start card: the flow's own settings when the definition has any.
     *
     * @return array{selectable: bool, chips: list<string>, problems: list<string>}
     */
    public function flowStartCard(): array
    {
        $definition = $this->flowDefinition();
        $flow = $this->currentFlow();

        return [
            'selectable' => $definition->settingsSchema() !== [],
            'chips' => $definition->startSummary($flow->settings),
            'problems' => [...(app(FlowValidator::class)->problems($flow, $definition)['start'] ?? []), ...($this->flowProblems[self::FLOW_START] ?? [])],
        ];
    }

    public function flowDefinition(): FlowDefinition
    {
        return app(FlowDefinitionRegistry::class)->get($this->flowDefinitionKey());
    }

    private function flowOutcomeLabel(Outcome $outcome, bool $last, FlowDefinition $definition): string
    {
        $where = match (true) {
            $outcome->goesTo === Outcome::START => $definition->returnLabel(),
            $outcome->goesTo === Outcome::FINISH, $last => $definition->finishLabel(),
            default => __('flow-builder::flow.goes_next'),
        };

        return $outcome->label.' → '.$where;
    }

    /**
     * Fills the panel through its schema, so every field of the step type and
     * of the chosen condition has its key (the browser binds to each one).
     *
     * @param  array<string, mixed>  $state
     */
    private function fillFlowPanel(array $state): void
    {
        unset($this->cachedSchemas['stepForm']);
        $this->getSchema('stepForm')?->fill($state);
    }

    private function selectFlowStart(): void
    {
        if ($this->flowDefinition()->settingsSchema() === []) {
            return;
        }

        $this->selected = self::FLOW_START;
        $this->stepData = [];
        unset($this->cachedSchemas['stepForm'], $this->cachedSchemas['settingsForm']);
        $this->getSchema('settingsForm')?->fill($this->currentFlow()->settings);
    }

    protected function currentFlow(): Flow
    {
        return Flow::fromArray($this->flow);
    }

    private function storeFlow(Flow $flow): void
    {
        $this->flow = $flow->toArray();
        $this->flowChanged();
    }
}
