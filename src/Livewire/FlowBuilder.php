<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Livewire;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\Validation\FlowValidator;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * The flow builder canvas: a column of cards between Start and Finish, a "+"
 * between every pair, drag to reorder, and a settings panel built from the
 * selected step type's Filament schema. The flow is a plain array, bound to
 * the parent with wire:model.
 */
class FlowBuilder extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    /** "Only when" with no condition. */
    private const string ALWAYS = '__always';

    /** @var array<string, mixed> */
    #[Modelable]
    public array $flow = ['steps' => []];

    #[Locked]
    public string $definition = '';

    #[Locked]
    public bool $readOnly = false;

    public ?string $selected = null;

    /** @var array<string, mixed> the selected step, as the panel edits it */
    public array $stepData = [];

    public function mount(): void
    {
        $this->flow = Flow::fromArray($this->flow)->toArray();
    }

    public function select(?string $id): void
    {
        $step = $id === null ? null : $this->current()->find($id);
        $this->selected = $step?->id;
        $this->stepData = [];
        unset($this->cachedSchemas['stepForm']);

        if ($step !== null) {
            $this->fillPanel([
                'name' => $step->name,
                'settings' => $step->settings,
                'when_type' => $step->when->type ?? self::ALWAYS,
                'when_settings' => $step->when->settings ?? [],
            ]);
        }
    }

    /** Adds a step of a registered type at a "+": 0 is right after Start. */
    public function insertStep(int $position, string $type): void
    {
        $definition = $this->definition();
        $stepType = $definition->stepType($type);
        $flow = $this->current();

        if ($this->readOnly || $stepType === null || count($flow->steps) >= $definition->maxSteps()) {
            return;
        }

        $step = new Step(Step::newId(), $type, $stepType->label(), $stepType->defaults($definition));
        $this->store($flow->insert($position, $step));
        $this->select($step->id);
    }

    public function removeStep(string $id): void
    {
        if ($this->readOnly) {
            return;
        }

        $this->store($this->current()->remove($id));

        if ($this->selected === $id) {
            $this->select(null);
        }
    }

    /** Drops a step on a "+": it then sits before the step now at $position. */
    public function moveStep(string $id, int $position): void
    {
        if (! $this->readOnly) {
            $this->store($this->current()->move($id, $position));
        }
    }

    public function moveStepUp(string $id): void
    {
        $index = $this->current()->indexOf($id);

        if ($index !== null && $index > 0) {
            $this->moveStep($id, $index - 1);
        }
    }

    public function moveStepDown(string $id): void
    {
        $index = $this->current()->indexOf($id);

        if ($index !== null) {
            $this->moveStep($id, $index + 2);
        }
    }

    /** The panel edits the selected step; every change goes straight back into the flow. */
    public function updatedStepData(mixed $value = null, ?string $key = null): void
    {
        // A different condition brings different fields: give each of them its key.
        if ($key === 'when_type' && ! $this->readOnly) {
            $this->fillPanel([...$this->stepData, 'when_settings' => []]);
        }

        $flow = $this->current();
        $step = $this->selected === null ? null : $flow->find($this->selected);

        if ($this->readOnly || $step === null) {
            return;
        }

        $whenType = is_string($this->stepData['when_type'] ?? null) ? $this->stepData['when_type'] : self::ALWAYS;

        $this->store($flow->replace($step->with([
            'name' => is_string($this->stepData['name'] ?? null) ? $this->stepData['name'] : '',
            'settings' => $this->stepData['settings'] ?? [],
            'when' => in_array($whenType, ['', self::ALWAYS], true) ? null : ['type' => $whenType, 'settings' => $this->stepData['when_settings'] ?? []],
        ])));
    }

    public function stepForm(Schema $schema): Schema
    {
        $definition = $this->definition();
        $step = $this->selected === null ? null : $this->current()->find($this->selected);
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

        if ($conditions !== []) {
            $components[] = Select::make('when_type')
                ->label(__('flow-builder::flow.panel.when'))
                ->options([self::ALWAYS => __('flow-builder::flow.panel.always'), ...array_map(static fn ($c): string => $c->label(), $conditions)])
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
            ->disabled($this->readOnly)
            ->components($components);
    }

    /**
     * What the canvas draws, in running order.
     *
     * @return list<array{id: string, number: int, kind: string, name: string, icon: string, color: string, chips: list<string>, outcomes: list<array{label: string, tone: string}>, condition: string|null, problems: list<string>}>
     */
    public function cards(): array
    {
        $definition = $this->definition();
        $flow = $this->current();
        $problems = app(FlowValidator::class)->problems($flow, $definition);
        $last = count($flow->steps) - 1;
        $cards = [];

        foreach ($flow->steps as $index => $step) {
            $type = $definition->stepType($step->type);
            $condition = $step->when === null ? null : $definition->conditionType($step->when->type);

            $cards[] = [
                'id' => $step->id,
                'number' => $index + 1,
                'kind' => $type?->label() ?? $step->type,
                'name' => $step->name,
                'icon' => $type?->icon() ?? 'heroicon-m-question-mark-circle',
                'color' => $type?->color() ?? 'gray',
                'chips' => $type?->summary($step, $definition) ?? [],
                'outcomes' => array_map(fn (Outcome $outcome): array => [
                    'label' => $this->outcomeLabel($outcome, $index === $last, $definition),
                    'tone' => $outcome->tone,
                ], $type?->outcomes($step, $definition) ?? []),
                'condition' => $condition?->describe($step->when->settings ?? [], $definition),
                'problems' => $problems[$step->id] ?? [],
            ];
        }

        return $cards;
    }

    public function definition(): FlowDefinition
    {
        return app(FlowDefinitionRegistry::class)->get($this->definition);
    }

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'flow-builder::livewire.flow-builder';

        return view($view, ['cards' => $this->cards(), 'flowDefinition' => $this->definition()]);
    }

    private function outcomeLabel(Outcome $outcome, bool $last, FlowDefinition $definition): string
    {
        $where = match (true) {
            $outcome->goesTo === Outcome::START => __('flow-builder::flow.goes_start'),
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
    private function fillPanel(array $state): void
    {
        unset($this->cachedSchemas['stepForm']);
        $this->getSchema('stepForm')?->fill($state);
    }

    private function current(): Flow
    {
        return Flow::fromArray($this->flow);
    }

    private function store(Flow $flow): void
    {
        $this->flow = $flow->toArray();
    }
}
