<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\ApprovalFlows\Pages;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Filament\Resources\ApprovalFlows\ApprovalFlowResource;
use Filament\Resources\Pages\EditRecord;

class EditApprovalFlow extends EditRecord
{
    protected static string $resource = ApprovalFlowResource::class;
}
