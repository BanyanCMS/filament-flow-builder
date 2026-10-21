<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\PurchaseRequests\Pages;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\PurchaseRequests\PurchaseRequestResource;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\PurchaseRequests\WorkflowActions;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseRequest extends ViewRecord
{
    protected static string $resource = PurchaseRequestResource::class;

    protected function getHeaderActions(): array
    {
        return WorkflowActions::all();
    }
}
