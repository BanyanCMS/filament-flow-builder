<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A flow people designed in the builder.
 *
 * @property int $id
 * @property string $name
 * @property array<string, mixed> $flow
 */
class ApprovalFlow extends Model
{
    protected $table = 'approval_flows';

    protected $guarded = [];

    protected $casts = ['flow' => 'array'];
}
