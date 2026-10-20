<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Tests\Support;

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Steps\ApprovalStep;

/** A purchase request: the kind of flow an app defines in a few lines. */
class PurchaseFlow extends FlowDefinition
{
    public static function key(): string
    {
        return 'purchase';
    }

    protected function stepTypeClasses(): array
    {
        return [ApprovalStep::class, BudgetCheckStep::class];
    }

    public function startLabel(): string
    {
        return 'Staff member submits a purchase';
    }

    public function finishLabel(): string
    {
        return 'Order placed';
    }

    public function approvers(): array
    {
        return ['manager' => 'Line manager', 'finance' => 'Finance team', 'director' => 'Director'];
    }

    public function contextFields(): array
    {
        return ['amount' => 'Amount (HKD)', 'department' => 'Department'];
    }
}
