<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Banyan\FlowBuilder\Forms\FlowBuilderField;
use Banyan\FlowBuilder\Tests\Support\PurchaseFlow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

/** The workbench page: a purchase approval flow, saved to the session. */
final class EditPurchaseFlow extends Page
{
    protected static ?string $slug = 'purchase-flow';

    protected static ?string $title = 'Purchase approval';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(['flow' => session('purchase-flow', ['steps' => []])]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            FlowBuilderField::make('flow')->hiddenLabel()->definition(PurchaseFlow::class),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }

    /** @return list<Action> */
    protected function getHeaderActions(): array
    {
        return [Action::make('save')->label('Save')->action(function (): void {
            session(['purchase-flow' => $this->form->getState()['flow']]);
            Notification::make()->success()->title('Saved')->send();
        })];
    }
}
