<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\PurchaseRequests;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\ApprovalFlow;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\PurchaseRequest;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models\RequestHistory;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Purchase requests: staff create and submit them; approvers act on the step that waits for them. */
class PurchaseRequestResource extends Resource
{
    protected static ?string $model = PurchaseRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required(),
            TextInput::make('amount')->label('Amount (HKD)')->numeric()->minValue(1)->required(),
            Select::make('department')->options(['IT' => 'IT', 'HR' => 'HR', 'Marketing' => 'Marketing'])->required(),
            Select::make('approval_flow_id')->label('Approval flow')->options(fn (): array => ApprovalFlow::query()->pluck('name', 'id')->all())->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')->columnSpanFull()->columns(3)->schema([
                TextEntry::make('title'),
                TextEntry::make('amount')->label('Amount (HKD)')->numeric(),
                TextEntry::make('department'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (string $state): string => self::statusLabel($state))->color(fn (string $state): string => self::statusColor($state)),
                TextEntry::make('current_step')->label('Waiting at')->placeholder('—'),
                TextEntry::make('step_reached_at')->label('Since')->since()->placeholder('—'),
            ]),
            Section::make('History')->columnSpanFull()->description('The audit log: every step, decision and reminder, oldest first.')->schema([
                RepeatableEntry::make('history')->hiddenLabel()->table([
                    TableColumn::make('When'),
                    TableColumn::make('What happened'),
                    TableColumn::make('Who'),
                ])->schema([
                    TextEntry::make('created_at')->dateTime('j M Y H:i'),
                    TextEntry::make('event')->state(fn (RequestHistory $record): string => $record->describe()),
                    TextEntry::make('actor.name')->placeholder('The system'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('amount')->label('HKD')->numeric(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => self::statusLabel($state))->color(fn (string $state): string => self::statusColor($state)),
                TextColumn::make('current_step')->label('Waiting at')->placeholder('—'),
            ])
            ->recordActions([ViewAction::make(), ...WorkflowActions::all()]);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            PurchaseRequest::DRAFT => 'Draft',
            PurchaseRequest::IN_REVIEW => 'In review',
            PurchaseRequest::CHANGES_REQUESTED => 'Changes requested',
            PurchaseRequest::APPROVED => 'Approved',
            PurchaseRequest::STUCK => 'Stuck: nobody can act',
            default => $status,
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            PurchaseRequest::APPROVED => 'success',
            PurchaseRequest::IN_REVIEW => 'info',
            PurchaseRequest::CHANGES_REQUESTED => 'warning',
            PurchaseRequest::STUCK => 'danger',
            default => 'gray',
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseRequests::route('/'),
            'create' => Pages\CreatePurchaseRequest::route('/create'),
            'view' => Pages\ViewPurchaseRequest::route('/{record}'),
        ];
    }
}
