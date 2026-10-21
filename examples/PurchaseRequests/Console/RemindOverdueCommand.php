<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Console;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\PurchaseRequestWorkflow;
use Illuminate\Console\Command;

/** Schedule it: Schedule::command('purchase-requests:remind')->hourly(); */
class RemindOverdueCommand extends Command
{
    protected $signature = 'purchase-requests:remind';

    protected $description = 'Remind approvers of purchase requests waiting longer than their step\'s "Remind after" days';

    public function handle(PurchaseRequestWorkflow $workflow): int
    {
        $this->info($workflow->remindOverdue().' request(s) reminded.');

        return self::SUCCESS;
    }
}
