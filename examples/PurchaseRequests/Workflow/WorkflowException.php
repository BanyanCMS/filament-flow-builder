<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow;

use RuntimeException;

/** Something a person tried that the workflow does not allow, in words you can show them. */
class WorkflowException extends RuntimeException {}
