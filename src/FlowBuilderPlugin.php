<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder;

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * ->plugin(FlowBuilderPlugin::make()->definitions([LeaveRequestFlow::class]))
 *
 * The plugin adds no pages, resources or routes of its own: the builder is a
 * form field and a Livewire component that you put on your own pages. What a
 * panel contributes is its flow definitions. They go into the app-wide
 * {@see FlowDefinitionRegistry}, so with two panels the definitions are shared,
 * not scoped to the panel that listed them.
 */
class FlowBuilderPlugin implements Plugin
{
    /** @var list<class-string<FlowDefinition>> */
    protected array $definitions = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'flow-builder';
    }

    /** @param list<class-string<FlowDefinition>> $definitions */
    public function definitions(array $definitions): static
    {
        $this->definitions = [...$this->definitions, ...$definitions];

        return $this;
    }

    /** @return list<class-string<FlowDefinition>> */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    public function register(Panel $panel): void
    {
        $registry = app(FlowDefinitionRegistry::class);

        foreach ($this->definitions as $definition) {
            $registry->register($definition);
        }
    }

    public function boot(Panel $panel): void {}
}
