<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $purchase_request_id
 * @property string $step_id
 * @property int $user_id
 * @property string|null $team
 * @property string $decision
 * @property string|null $note
 */
class RequestVote extends Model
{
    protected $table = 'purchase_request_votes';

    protected $guarded = [];
}
