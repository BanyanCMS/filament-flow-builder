<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Tests\Support;

/** A host's own subclass of {@see PurchaseFlow}, registered under the same key to swap it. */
final class RivalPurchaseFlow extends PurchaseFlow
{
    // Inherits key() 'purchase' on purpose.
}
