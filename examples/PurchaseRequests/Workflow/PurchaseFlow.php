<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow;

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Steps\ApprovalStep;
use Banyan\FilamentFlowBuilder\Steps\ParallelApprovalStep;

/**
 * What a purchase approval flow may contain. The keys here (manager,
 * finance, …) are roles, not people: ApproverDirectory says who holds them
 * in your system, so the same flow works as staff come and go.
 */
class PurchaseFlow extends FlowDefinition
{
    public static function key(): string
    {
        return 'purchase-requests';
    }

    public function label(): string
    {
        return 'Purchase approval';
    }

    protected function stepTypeClasses(): array
    {
        return [ApprovalStep::class, ParallelApprovalStep::class];
    }

    public function startLabel(): string
    {
        return 'A staff member submits a purchase request';
    }

    public function finishLabel(): string
    {
        return 'Approved: order it';
    }

    public function approvers(): array
    {
        return ['manager' => 'Line manager', 'finance' => 'Finance', 'director' => 'Director'];
    }

    public function teams(): array
    {
        return ['finance' => 'Finance', 'it' => 'IT', 'legal' => 'Legal'];
    }

    public function contextFields(): array
    {
        return ['amount' => 'Amount (HKD)', 'department' => 'Department'];
    }
}
