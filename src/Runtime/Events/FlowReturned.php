<?php

declare(strict_types=1);

namespace Banyan\FlowBuilder\Runtime\Events;

use Banyan\FlowBuilder\Data\Flow;
use Banyan\FlowBuilder\Data\Step;

/** The item went back to whoever started it (e.g. "Request changes"). */
final readonly class FlowReturned
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $definition,
        public Flow $flow,
        public Step $from,
        public array $context,
    ) {}
}
