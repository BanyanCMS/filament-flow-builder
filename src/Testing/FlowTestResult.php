<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Testing;

/** What "Test it as…" found: one plain line per step, and where the item ends up. */
final readonly class FlowTestResult
{
    /** @param list<string> $lines */
    public function __construct(
        public array $lines,
        public string $outcome,
    ) {}
}
