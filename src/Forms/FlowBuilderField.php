<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder\Forms;

use Banyan\FlowBuilder\Data\Flow;
use Banyan\FlowBuilder\Definitions\FlowDefinition;
use Banyan\FlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FlowBuilder\Validation\FlowValidator;
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

    /** @param class-string<FlowDefinition>|string $definition a definition class, or the key it was registered under */
    public function definition(string $definition): static
    {
        $registry = app(FlowDefinitionRegistry::class);

        if (is_subclass_of($definition, FlowDefinition::class)) {
            if (! $registry->has($definition::key())) {
                $registry->register($definition);
            }

            $definition = $definition::key();
        }

        if (! $registry->has($definition)) {
            throw new InvalidArgumentException("No flow definition is registered as [{$definition}].");
        }

        $this->definitionKey = $definition;

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
