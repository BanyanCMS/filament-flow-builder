<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Runtime\Events;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Step;

/** A step's condition did not hold, so the item went past it. */
final readonly class StepSkipped
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $definition,
        public Flow $flow,
        public Step $step,
        public array $context,
    ) {}
}
