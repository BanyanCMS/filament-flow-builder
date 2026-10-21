<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property int $amount
 * @property string $department
 * @property int $requester_id
 * @property int $approval_flow_id
 * @property array<string, mixed>|null $flow
 * @property array<string, mixed>|null $position
 * @property string $status
 * @property string|null $current_step
 * @property Carbon|null $step_reached_at
 * @property Carbon|null $reminded_at
 */
class PurchaseRequest extends Model
{
    public const string DRAFT = 'draft';

    /** Waiting for the people of its current step. */
    public const string IN_REVIEW = 'in_review';

    /** Someone asked for changes: back with the requester, who can submit again. */
    public const string CHANGES_REQUESTED = 'changes_requested';

    /** Every step is done. */
    public const string APPROVED = 'approved';

    /** Its current step has nobody who can act: an administrator must assign someone, then retry. */
    public const string STUCK = 'stuck';

    protected $table = 'purchase_requests';

    protected $guarded = [];

    protected $casts = ['flow' => 'array', 'position' => 'array', 'step_reached_at' => 'datetime', 'reminded_at' => 'datetime'];

    /** @return BelongsTo<ApprovalFlow, $this> */
    public function approvalFlow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class);
    }

    /** @return HasMany<RequestHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(RequestHistory::class)->orderBy('id');
    }

    /** @return HasMany<RequestVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(RequestVote::class);
    }

    /**
     * What the runner and conditions may look at: the context fields of the definition.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['amount' => $this->amount, 'department' => $this->department, 'purchase_request_id' => $this->id];
    }
}
