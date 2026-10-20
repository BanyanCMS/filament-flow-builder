<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Runtime\Events;

use Banyan\FilamentFlowBuilder\Data\Flow;

/** Every step is done: publish, pay, book — whatever your finish means. */
final readonly class FlowFinished
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $definition,
        public Flow $flow,
        public array $context,
    ) {}
}
