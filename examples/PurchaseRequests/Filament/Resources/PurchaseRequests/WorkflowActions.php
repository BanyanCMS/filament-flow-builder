<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\PurchaseRequests;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\PurchaseRequest;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\PurchaseRequestWorkflow;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\WorkflowException;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * The buttons people press. Each one is visible only to whoever may use it
 * now, and every refusal from the workflow is shown as a plain message.
 */
class WorkflowActions
{
    /** @return list<Action> */
    public static function all(): array
    {
        return [self::submit(), self::approve(), self::requestChanges(), self::retry()];
    }

    public static function submit(): Action
    {
        return Action::make('submit')
            ->label('Submit for approval')
            ->icon('heroicon-m-paper-airplane')
            ->visible(fn (PurchaseRequest $record): bool => $record->requester_id === auth()->id() && in_array($record->status, [PurchaseRequest::DRAFT, PurchaseRequest::CHANGES_REQUESTED], true))
            ->action(self::run(fn (PurchaseRequestWorkflow $w, PurchaseRequest $r) => $w->submit($r, (int) auth()->id()), 'Submitted.'));
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-m-check')
            ->color('success')
            ->visible(fn (PurchaseRequest $record): bool => app(PurchaseRequestWorkflow::class)->canAct($record, (int) auth()->id()))
            ->schema([Textarea::make('note')->label('Note (optional)')])
            ->action(self::run(fn (PurchaseRequestWorkflow $w, PurchaseRequest $r, array $data) => $w->approve($r, (int) auth()->id(), $data['note'] ?? null), 'Approved.'));
    }

    public static function requestChanges(): Action
    {
        return Action::make('requestChanges')
            ->label('Request changes')
            ->icon('heroicon-m-arrow-uturn-left')
            ->color('warning')
            ->visible(fn (PurchaseRequest $record): bool => app(PurchaseRequestWorkflow::class)->canAct($record, (int) auth()->id()))
            ->schema([Textarea::make('note')->label('What needs to change?')->required()])
            ->action(self::run(fn (PurchaseRequestWorkflow $w, PurchaseRequest $r, array $data) => $w->requestChanges($r, (int) auth()->id(), (string) $data['note']), 'Sent back to the requester.'));
    }

    public static function retry(): Action
    {
        return Action::make('retry')
            ->label('Try the step again')
            ->icon('heroicon-m-arrow-path')
            ->color('danger')
            ->visible(fn (PurchaseRequest $record): bool => $record->status === PurchaseRequest::STUCK)
            ->action(self::run(fn (PurchaseRequestWorkflow $w, PurchaseRequest $r) => $w->retry($r, (int) auth()->id()), 'Tried again.'));
    }

    /** Runs a workflow call and turns its refusals into a message instead of an error page. */
    private static function run(Closure $call, string $done): Closure
    {
        return function (PurchaseRequest $record, array $data = []) use ($call, $done): void {
            try {
                $call(app(PurchaseRequestWorkflow::class), $record, $data);
                Notification::make()->success()->title($done)->send();
            } catch (WorkflowException $refused) {
                Notification::make()->danger()->title($refused->getMessage())->send();
            }
        };
    }
}
