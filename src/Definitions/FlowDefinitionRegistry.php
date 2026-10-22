<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Definitions;

use InvalidArgumentException;

/**
 * The flow definitions an app registered, by key. The builder component is
 * given a key, never a class name from the browser.
 *
 * It is one registry for the whole app (a container singleton), not one per
 * panel: every panel's plugin registers into it, so a definition added by one
 * panel can be named by a component in another. The last class registered
 * under a key wins, which is how an app swaps a definition for its own
 * subclass; it also means two panels must not reuse a key for different flows.
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
