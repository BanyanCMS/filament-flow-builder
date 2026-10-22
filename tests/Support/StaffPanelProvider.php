<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Tests\Support;

use Banyan\FilamentFlowBuilder\FlowBuilderPlugin;
use Filament\Panel;
use Filament\PanelProvider;

/** The second panel of the multi-panel tests: the leave flow. */
final class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('staff')->path('staff')
            ->pages([LeaveFlowPage::class])
            ->plugin(FlowBuilderPlugin::make()->definitions([LeaveFlow::class]));
    }
}
