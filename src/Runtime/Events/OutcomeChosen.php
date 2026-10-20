<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder\Runtime\Events;

use Banyan\FlowBuilder\Data\Flow;
use Banyan\FlowBuilder\Data\Outcome;
use Banyan\FlowBuilder\Data\Step;

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
