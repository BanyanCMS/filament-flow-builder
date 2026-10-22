<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Tests\Support;

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Steps\ApprovalStep;

/** A second flow, owned by the "staff" panel in the multi-panel tests. */
final class LeaveFlow extends FlowDefinition
{
    public static function key(): string
    {
        return 'leave';
    }

    protected function stepTypeClasses(): array
    {
        return [ApprovalStep::class];
    }

    public function startLabel(): string
    {
        return 'Staff member asks for leave';
    }

    public function approvers(): array
    {
        return ['supervisor' => 'Supervisor'];
    }
}
