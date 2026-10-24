<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Definitions;

use Banyan\FilamentFlowBuilder\Conditions\ConditionType;
use Banyan\FilamentFlowBuilder\Conditions\ValueCondition;
use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Step;
use Banyan\FilamentFlowBuilder\DryRun\DryRunResult;
use Banyan\FilamentFlowBuilder\DryRun\FlowWalker;
use Banyan\FilamentFlowBuilder\Steps\ApprovalStep;
use Banyan\FilamentFlowBuilder\Steps\StepType;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

/**
 * One kind of flow in your app ("Leave request", "Purchase approval"): which
 * step and condition types designers may use, what the start and finish
 * mean, and the choices the built-in types need. Extend it and register it
 * on the plugin.
 */
abstract class FlowDefinition
{
    /** @var array<string, StepType>|null */
    private ?array $stepTypes = null;

    /** @var array<string, ConditionType>|null */
    private ?array $conditionTypes = null;

    /** Stored with the builder; lowercase letters, digits, dashes. */
    abstract public static function key(): string;

    public function label(): string
    {
        return Str::headline(static::key());
    }

    /** @return list<class-string<StepType>> */
    protected function stepTypeClasses(): array
    {
        return [ApprovalStep::class];
    }

    /** @return list<class-string<ConditionType>> */
    protected function conditionTypeClasses(): array
    {
        return [ValueCondition::class];
    }

    /** The start card: who sets the flow going. */
    public function startLabel(): string
    {
        return (string) __('flow-builder::flow.start_name');
    }

    /** The finish card: what happens when every step is done. */
    public function finishLabel(): string
    {
        return (string) __('flow-builder::flow.finish_name');
    }

    /** @return array<string, string> approver id => name, for the built-in approval step */
    public function approvers(): array
    {
        return [];
    }

    /** @return array<string, string> context key => label, for the built-in value condition */
    public function contextFields(): array
    {
        return [];
    }

    /** @return array<string, string> team id => name, for the built-in parallel step */
    public function teams(): array
    {
        return [];
    }

    /**
     * The flow's own settings, edited when someone selects the Start card
     * (name, what it applies to, …). Stored in the flow under "settings".
     *
     * @return list<Component|Field>
     */
    public function settingsSchema(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string> chips on the Start card
     */
    public function startSummary(array $settings): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string> what is wrong with the flow's own settings
     */
    public function validateSettings(array $settings): array
    {
        return [];
    }

    /**
     * What "Test it as…" asks for: by default one field per context value.
     *
     * @return list<Component|Field>
     */
    public function dryRunSchema(): array
    {
        $fields = [];

        foreach ($this->contextFields() as $key => $label) {
            $fields[] = TextInput::make($key)->label($label)->live(onBlur: true);
        }

        return $fields;
    }

    /**
     * "Test it as…": what happens to an item with this input. Override it to
     * run your own engine; the default walks the flow on screen.
     *
     * @param  array<string, mixed>  $input
     */
    public function dryRun(Flow $flow, array $input): DryRunResult
    {
        return app(FlowWalker::class)->walk($this, $flow, $input);
    }

    public function maxSteps(): int
    {
        return 30;
    }

    /** The id a new step gets; override to match ids your app already stores. */
    public function newStepId(): string
    {
        return Step::newId();
    }

    /** The heading of the Start card's panel. */
    public function settingsLabel(): string
    {
        return __('flow-builder::flow.panel.flow_settings');
    }

    /** What a card says when an outcome sends the item back to the start. */
    public function returnLabel(): string
    {
        return __('flow-builder::flow.goes_start');
    }

    /** @return array<string, StepType> */
    public function stepTypes(): array
    {
        if ($this->stepTypes === null) {
            $this->stepTypes = [];

            foreach ($this->stepTypeClasses() as $class) {
                $this->stepTypes[$class::key()] = app($class);
            }
        }

        return $this->stepTypes;
    }

    /** @return array<string, ConditionType> */
    public function conditionTypes(): array
    {
        if ($this->conditionTypes === null) {
            $this->conditionTypes = [];

            foreach ($this->conditionTypeClasses() as $class) {
                $this->conditionTypes[$class::key()] = app($class);
            }
        }

        return $this->conditionTypes;
    }

    public function stepType(string $key): ?StepType
    {
        return $this->stepTypes()[$key] ?? null;
    }

    public function conditionType(string $key): ?ConditionType
    {
        return $this->conditionTypes()[$key] ?? null;
    }
}
