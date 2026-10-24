<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Tests\Support;

/** A purchase flow that counts how often the container builds it. */
final class CountingFlow extends PurchaseFlow
{
    public static int $built = 0;

    public static function key(): string
    {
        return 'counting';
    }

    public function __construct()
    {
        self::$built++;
    }
}
