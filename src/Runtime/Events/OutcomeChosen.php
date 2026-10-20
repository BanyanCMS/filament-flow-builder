<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Runtime\Events;

use Banyan\FilamentFlowBuilder\Data\Flow;
use Banyan\FilamentFlowBuilder\Data\Outcome;
use Banyan\FilamentFlowBuilder\Data\Step;

/** Someone chose an outcome on a step ("Approve", "Request changes"). */
final readonly class OutcomeChosen
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $definition,
        public Flow $flow,
        public Step $step,
        public Outcome $outcome,
        public array $context,
    ) {}
}
