<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder\Definitions;

use Banyan\FlowBuilder\Conditions\ConditionType;
use Banyan\FlowBuilder\Conditions\ValueCondition;
use Banyan\FlowBuilder\Steps\ApprovalStep;
use Banyan\FlowBuilder\Steps\StepType;
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

    public function maxSteps(): int
    {
        return 30;
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
