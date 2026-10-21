<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\PurchaseRequests\Pages;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPurchaseRequests extends ListRecords
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
