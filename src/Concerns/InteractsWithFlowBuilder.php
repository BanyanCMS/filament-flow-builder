<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Concerns;

use Banyan\FilamentFlowBuilder\Actions\DryRunAction;
use Banyan\FilamentFlowBuilder\Canvas\CanvasBuilder;
use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Data\StepPreset;
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
 * built-in "Test it as…" dry run. The Livewire members the trait adds all start
 * with "flow" (or end in "Flow…"), so they do not clash with the host's own. It renders the canvas with
 * `@include('flow-builder::canvas')` and the action
 * modals with `<x-filament-actions::modals />` (a Filament page already does).
 */
trait InteractsWithFlowBuilder
{
    /** "Only when" with no condition. */
    public const string FLOW_ALWAYS = '__always';

    /** The Start card's selection id. */
    public const string FLOW_START = CanvasBuilder::START;

    private const string PANEL_WHEN_TYPE = 'when_type';

    private const string PANEL_WHEN_SETTINGS = 'when_settings';

    private const string PANEL_REMINDER_DAYS = 'reminder_days';

    public ?string $flowSelected = null;

    /** @var array<string, mixed> the selected step, as the panel edits it */
    public array $flowStepData = [];

    /** @var array<string, mixed> the flow's own settings, as the Start panel edits them */
    public array $flowSettings = [];

    /** @var array<string, list<string>> step id (or "start") => problems the host found, e.g. on save */
    public array $flowProblems = [];

    /** Not Livewire state: only public properties are sent to the browser. */
    protected ?FlowDefinition $resolvedFlowDefinition = null;

    abstract public function flowDefinitionKey(): string;

    public function flowIsReadOnly(): bool
    {
        return false;
    }

    public function flowHasDryRun(): bool
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
     * @internal
     *
     * @return array{cards: list<array<string, mixed>>, start: array{selectable: bool, chips: list<string>, problems: list<string>}, flowDefinition: FlowDefinition}
     */
    public function flowCanvas(): array
    {
        $canvas = $this->canvasBuilder();

        return ['cards' => $canvas->cards(), 'start' => $canvas->start(), 'flowDefinition' => $this->flowDefinition()];
    }

    public function selectFlowNode(?string $id): void
    {
        // Errors under the panel's fields belong to what was selected before.
        $panelErrors = array_values(array_filter(
            $this->getErrorBag()->keys(),
            static fn (string $key): bool => str_starts_with($key, 'flowStepData.') || str_starts_with($key, 'flowSettings.'),
        ));

        if ($panelErrors !== []) {
            $this->resetErrorBag($panelErrors);
        }

        if ($id === self::FLOW_START) {
            $this->selectFlowStart();

            return;
        }

        $step = $id === null ? null : $this->currentFlow()->find($id);
        $this->flowSelected = $step?->id;
        $this->flowStepData = [];
        $this->forgetSchemas('flowStepForm');

        if ($step !== null) {
            $this->fillFlowPanel([
                'name' => $step->name,
                'settings' => $step->settings,
                self::PANEL_WHEN_TYPE => $step->when->type ?? self::FLOW_ALWAYS,
                self::PANEL_WHEN_SETTINGS => $step->when->settings ?? [],
                self::PANEL_REMINDER_DAYS => $step->reminderDays,
            ]);
        }
    }

    /** Adds a step at a "+" (0 is right after Start), from one of its type's presets. */
    public function insertFlowStep(int $position, string $type, string $preset = 'default'): void
    {
        $definition = $this->flowDefinition();
        $stepType = $definition->stepType($type);
        $flow = $this->currentFlow();
        $chosen = $this->findPreset($stepType?->presets($definition) ?? [], $preset);

        if ($this->flowIsReadOnly() || $chosen === null) {
            return;
        }

        if (count($flow->steps) >= $definition->maxSteps()) {
            $this->flowFull($definition->maxSteps());

            return;
        }

        $step = new Step($definition->newStepId(), $type, $chosen->name, $chosen->settings, $chosen->when, $chosen->reminderDays);
        $this->storeFlow($flow->insert($position, $step));
        $this->selectFlowNode($step->id);
    }

    /**
     * The preset with this key (the last one if a type repeats a key), or null.
     *
     * @param  list<StepPreset>  $presets
     */
    private function findPreset(array $presets, string $key): ?StepPreset
    {
        $found = null;

        foreach ($presets as $candidate) {
            $found = $candidate->key === $key ? $candidate : $found;
        }

        return $found;
    }

    public function removeFlowStep(string $id): void
    {
        if ($this->flowIsReadOnly()) {
            return;
        }

        $this->storeFlow($this->currentFlow()->remove($id));

        if ($this->flowSelected === $id) {
            $this->selectFlowNode($this->flowDefinition()->settingsSchema() === [] ? null : self::FLOW_START);
        }
    }

    /** Drops a step on a "+": it then sits before the step now at $position. */
    public function moveFlowStep(string $id, int $position): void
    {
        if (! $this->flowIsReadOnly()) {
            $this->storeFlow($this->currentFlow()->move($id, $position));
        }
    }

    public function moveFlowStepUp(string $id): void
    {
        $index = $this->currentFlow()->indexOf($id);

        if ($index !== null && $index > 0) {
            $this->moveFlowStep($id, $index - 1);
        }
    }

    public function moveFlowStepDown(string $id): void
    {
        $index = $this->currentFlow()->indexOf($id);

        if ($index !== null) {
            // Moving to position p puts the step before the one now at p, so one place down is index + 2.
            $this->moveFlowStep($id, $index + 2);
        }
    }

    /** The panel edits the selected step; every change goes straight back into the flow. */
    public function updatedFlowStepData(mixed $value = null, ?string $key = null): void
    {
        if ($this->flowIsReadOnly()) {
            return;
        }

        // A different condition brings different fields: give each of them its key, starting from the condition's defaults.
        if ($key === self::PANEL_WHEN_TYPE) {
            $condition = is_string($value) ? $this->flowDefinition()->conditionType($value) : null;
            $this->fillFlowPanel([...$this->flowStepData, self::PANEL_WHEN_SETTINGS => $condition?->defaults($this->flowDefinition()) ?? []]);
        }

        $flow = $this->currentFlow();
        $step = $this->flowSelected === null ? null : $flow->find($this->flowSelected);

        if ($step !== null) {
            $this->storeFlow($flow->replace($step->with($this->panelChanges())));
        }
    }

    /**
     * The panel's state as the changes `Step::with()` takes.
     *
     * @return array<string, mixed>
     */
    private function panelChanges(): array
    {
        $whenType = is_string($this->flowStepData[self::PANEL_WHEN_TYPE] ?? null) ? $this->flowStepData[self::PANEL_WHEN_TYPE] : self::FLOW_ALWAYS;

        return [
            'name' => is_string($this->flowStepData['name'] ?? null) ? $this->flowStepData['name'] : '',
            'settings' => $this->flowStepData['settings'] ?? [],
            'when' => in_array($whenType, ['', self::FLOW_ALWAYS], true) ? null : ['type' => $whenType, 'settings' => $this->flowStepData[self::PANEL_WHEN_SETTINGS] ?? []],
            'reminder_days' => $this->flowStepData[self::PANEL_REMINDER_DAYS] ?? null,
        ];
    }

    /** The Start panel edits the flow's own settings. */
    public function updatedFlowSettings(): void
    {
        if (! $this->flowIsReadOnly()) {
            $this->storeFlow($this->currentFlow()->withSettings($this->flowSettings));
        }
    }

    public function flowSettingsForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('flowSettings')
            ->disabled($this->flowIsReadOnly())
            ->components($this->flowSelected === self::FLOW_START ? $this->flowDefinition()->settingsSchema() : []);
    }

    public function flowDryRunAction(): Action
    {
        return DryRunAction::make('flowDryRun')->definition($this->flowDefinitionKey())->flowUsing(fn (): Flow => $this->currentFlow());
    }

    public function flowStepForm(Schema $schema): Schema
    {
        $definition = $this->flowDefinition();
        $step = $this->flowSelected === null ? null : $this->currentFlow()->find($this->flowSelected);
        $type = $step === null ? null : $definition->stepType($step->type);

        if ($step === null || $type === null) {
            return $schema->statePath('flowStepData')->components([]);
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
            $components[] = TextInput::make(self::PANEL_REMINDER_DAYS)
                ->label(__('flow-builder::flow.panel.reminder'))
                ->helperText(__('flow-builder::flow.panel.reminder_help'))
                ->numeric()
                ->minValue(1)
                ->maxValue(365)
                ->suffix(__('flow-builder::flow.panel.days'))
                ->live(onBlur: true);
        }

        if ($conditions !== []) {
            $components[] = Select::make(self::PANEL_WHEN_TYPE)
                ->label(__('flow-builder::flow.panel.when'))
                ->options([self::FLOW_ALWAYS => __('flow-builder::flow.panel.always'), ...array_map(static fn ($c): string => $c->label(), $conditions)])
                ->selectablePlaceholder(false)
                ->native(false)
                ->live();

            foreach ($conditions as $key => $condition) {
                $components[] = Group::make($condition->schema($definition))
                    ->statePath(self::PANEL_WHEN_SETTINGS)
                    ->visible(fn (Get $get): bool => $get(self::PANEL_WHEN_TYPE) === $key);
            }
        }

        return $schema
            ->statePath('flowStepData')
            ->disabled($this->flowIsReadOnly())
            ->components($components);
    }

    /**
     * What the canvas draws, in running order.
     *
     * @internal the canvas view uses flowCanvas(); this stays for hosts that call it
     *
     * @return list<array{id: string, number: int, kind: string, name: string, icon: string, color: string, chips: list<string>, outcomes: list<array{label: string, tone: string}>, condition: string|null, problems: list<string>}>
     */
    public function flowCards(): array
    {
        return $this->canvasBuilder()->cards();
    }

    /**
     * The Start card: the flow's own settings when the definition has any.
     *
     * @internal the canvas view uses flowCanvas(); this stays for hosts that call it
     *
     * @return array{selectable: bool, chips: list<string>, problems: list<string>}
     */
    public function flowStartCard(): array
    {
        return $this->canvasBuilder()->start();
    }

    private function canvasBuilder(): CanvasBuilder
    {
        $flow = $this->currentFlow();
        $definition = $this->flowDefinition();

        return new CanvasBuilder($flow, $definition, app(FlowValidator::class)->problems($flow, $definition), $this->flowProblems);
    }

    /** The registry builds a new object on every call, so the first one is kept for the request. */
    public function flowDefinition(): FlowDefinition
    {
        return $this->resolvedFlowDefinition ??= app(FlowDefinitionRegistry::class)->get($this->flowDefinitionKey());
    }

    /**
     * Fills the panel through its schema, so every field of the step type and
     * of the chosen condition has its key (the browser binds to each one).
     *
     * @param  array<string, mixed>  $state
     */
    private function fillFlowPanel(array $state): void
    {
        $this->forgetSchemas('flowStepForm');
        $this->getSchema('flowStepForm')?->fill($state);
    }

    /** The named schemas are rebuilt the next time they are asked for. */
    private function forgetSchemas(string ...$names): void
    {
        foreach ($names as $name) {
            unset($this->cachedSchemas[$name]);
        }
    }

    private function selectFlowStart(): void
    {
        if ($this->flowDefinition()->settingsSchema() === []) {
            return;
        }

        $this->flowSelected = self::FLOW_START;
        $this->flowStepData = [];
        $this->forgetSchemas('flowStepForm', 'flowSettingsForm');
        $this->getSchema('flowSettingsForm')?->fill($this->currentFlow()->settings);
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
