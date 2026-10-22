<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Tests\Support;

use Banyan\FilamentFlowBuilder\Forms\FlowBuilderField;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Schema;

/** The admin panel's page: a flow builder field for the purchase flow. */
final class PurchaseFlowPage extends Page
{
    protected static ?string $slug = 'purchase-flow';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            FlowBuilderField::make('flow')->definition(PurchaseFlow::class),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedSchema::make('form')]);
    }
}
