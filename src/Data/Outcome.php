<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Data;

/**
 * A way out of a step ("Approve", "Request changes") and where it leads.
 */
final readonly class Outcome
{
    /** Go on to the next step whose condition holds, or finish after the last. */
    public const string NEXT = 'next';

    /** Back to the start: the flow is returned to whoever started it. */
    public const string START = 'start';

    /** Straight to the finish, skipping the remaining steps. */
    public const string FINISH = 'finish';

    /**
     * @param  'success'|'warning'|'danger'|'gray'|'primary'  $tone
     * @param  self::NEXT|self::START|self::FINISH  $goesTo
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $goesTo = self::NEXT,
        public string $tone = 'success',
    ) {}
}
