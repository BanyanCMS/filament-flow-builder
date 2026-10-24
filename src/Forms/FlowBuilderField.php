<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Forms;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\Validation\FlowValidator;
use Closure;
use Filament\Forms\Components\Field;
use InvalidArgumentException;

/**
 * The flow builder as a Filament form field: FlowBuilderField::make('flow')
 * ->definition(LeaveRequestFlow::class). Its state is the flow as an array
 * ({"steps": [...]}); a flow with problems does not save.
 */
class FlowBuilderField extends Field
{
    protected string $view = 'flow-builder::forms.flow-builder-field';

    protected string $definitionKey = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(['steps' => []]);
        $this->afterStateHydrated(static fn (FlowBuilderField $component, mixed $state) => $component->state(Flow::fromArray($state)->toArray()));
        $this->dehydrateStateUsing(static fn (mixed $state): array => Flow::fromArray($state)->toArray());
        $this->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            $problems = app(FlowValidator::class)->problems(Flow::fromArray($value), $this->getDefinition());

            if ($problems !== []) {
                $fail((string) __('flow-builder::flow.has_problems', ['count' => count(array_merge(...array_values($problems)))]));
            }
        });
    }

    /**
     * Names the flow definition to edit: its class, or the key it is registered under.
     * The definition must already be registered (FlowBuilderPlugin::definitions()),
     * because the builder looks it up again on every Livewire request.
     *
     * @param  class-string<FlowDefinition>|string  $definition
     */
    public function definition(string $definition): static
    {
        $key = is_subclass_of($definition, FlowDefinition::class) ? $definition::key() : $definition;

        if (! app(FlowDefinitionRegistry::class)->has($key)) {
            throw new InvalidArgumentException("No flow definition is registered as [{$key}]. Add [{$definition}] to FlowBuilderPlugin::make()->definitions([...]) on your panel.");
        }

        $this->definitionKey = $key;

        return $this;
    }

    public function getDefinitionKey(): string
    {
        return $this->definitionKey;
    }

    public function getDefinition(): FlowDefinition
    {
        return app(FlowDefinitionRegistry::class)->get($this->definitionKey);
    }
}
