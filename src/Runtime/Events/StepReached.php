<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Runtime\Events;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Step;

/**
 * An item arrived at a step. Listen to it to notify the step's approvers,
 * assign tasks or start a timer — the builder leaves that to your app.
 */
final readonly class StepReached
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $definition,
        public Flow $flow,
        public Step $step,
        public array $context,
    ) {}
}
