<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Tests\Support;

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinition;
use Banyan\FilamentFlowBuilder\Steps\ApprovalStep;
use Banyan\FilamentFlowBuilder\Steps\ParallelApprovalStep;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/** A purchase request: the kind of flow an app defines in a few lines. */
class PurchaseFlow extends FlowDefinition
{
    public static function key(): string
    {
        return 'purchase';
    }

    protected function stepTypeClasses(): array
    {
        return [ApprovalStep::class, BudgetCheckStep::class, ParallelApprovalStep::class];
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

    public function teams(): array
    {
        return ['legal' => 'Legal', 'it' => 'IT', 'finance' => 'Finance'];
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('title')->label('Flow name')->live(onBlur: true),
            Toggle::make('urgent')->label('Urgent purchases')->live(),
        ];
    }

    public function startSummary(array $settings): array
    {
        return is_string($settings['title'] ?? null) && $settings['title'] !== '' ? [$settings['title']] : [];
    }

    public function validateSettings(array $settings): array
    {
        // Only once someone has started on the settings, so older flows stay valid.
        return array_key_exists('title', $settings) && trim(is_string($settings['title']) ? $settings['title'] : '') === '' ? ['Give the flow a name.'] : [];
    }
}
