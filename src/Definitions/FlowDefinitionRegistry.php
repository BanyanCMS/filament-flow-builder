<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder\Definitions;

use InvalidArgumentException;

/**
 * The flow definitions an app registered, by key. The builder component is
 * given a key, never a class name from the browser.
 */
final class FlowDefinitionRegistry
{
    /** @var array<string, class-string<FlowDefinition>> */
    private array $definitions = [];

    /** @param class-string<FlowDefinition> $class */
    public function register(string $class): void
    {
        if (! is_subclass_of($class, FlowDefinition::class)) {
            throw new InvalidArgumentException("[{$class}] is not a flow definition.");
        }

        $this->definitions[$class::key()] = $class;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): FlowDefinition
    {
        $class = $this->definitions[$key] ?? throw new InvalidArgumentException("No flow definition is registered as [{$key}].");

        return app($class);
    }

    /** @return array<string, class-string<FlowDefinition>> */
    public function all(): array
    {
        return $this->definitions;
    }
}
