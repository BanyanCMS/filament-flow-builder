<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests;

use Banyan\FilamentFlowBuilder\Definitions\FlowDefinitionRegistry;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Console\RemindOverdueCommand;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\ApproverDirectory;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\DatabaseApproverDirectory;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\PurchaseFlow;
use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\RecordHistory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Wires the example into an app: tables, the definition, the user bridge, the audit log, the reminder command. */
class PurchaseRequestsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap this for your own users, roles or HR system.
        $this->app->singleton(ApproverDirectory::class, DatabaseApproverDirectory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        $this->app->make(FlowDefinitionRegistry::class)->register(PurchaseFlow::class);
        Event::subscribe(RecordHistory::class);

        if ($this->app->runningInConsole()) {
            $this->commands([RemindOverdueCommand::class]);
        }
    }
}
