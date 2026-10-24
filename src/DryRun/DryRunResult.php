<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\DryRun;

/** What "Test it as…" found: one plain line per step, and where the item ends up. */
final readonly class DryRunResult
{
    /** @param list<string> $lines */
    public function __construct(
        public array $lines,
        public string $outcome,
    ) {}
}
