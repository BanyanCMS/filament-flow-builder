<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\ApprovalFlows\Pages;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\ApprovalFlows\ApprovalFlowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApprovalFlows extends ListRecords
{
    protected static string $resource = ApprovalFlowResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
