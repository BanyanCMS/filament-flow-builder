<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Tests\Support;

use Banyan\FilamentFlowBuilder\FlowBuilderPlugin;
use Filament\Panel;
use Filament\PanelProvider;

/** The first panel of the multi-panel tests: the purchase flow. */
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')->path('admin')
            ->pages([PurchaseFlowPage::class])
            ->plugin(FlowBuilderPlugin::make()->definitions([PurchaseFlow::class]));
    }
}
