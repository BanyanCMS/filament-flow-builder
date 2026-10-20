<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder;

use Banyan\FlowBuilder\Definitions\FlowDefinition;
use Banyan\FlowBuilder\Definitions\FlowDefinitionRegistry;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * ->plugin(FlowBuilderPlugin::make()->definitions([LeaveRequestFlow::class]))
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

    public function register(Panel $panel): void
    {
        $registry = app(FlowDefinitionRegistry::class);

        foreach ($this->definitions as $definition) {
            $registry->register($definition);
        }
    }

    public function boot(Panel $panel): void {}
}
