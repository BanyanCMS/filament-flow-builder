<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Models;

use Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow\PurchaseFlow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a request's audit log.
 *
 * @property int $id
 * @property int $purchase_request_id
 * @property string $event submitted, step_reached, step_skipped, voted, outcome, returned, finished, stuck, reminded
 * @property string|null $step_id
 * @property string|null $step_name
 * @property int|null $actor_id
 * @property array<string, mixed>|null $details
 * @property Carbon $created_at
 */
class RequestHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'purchase_request_history';

    protected $guarded = [];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];

    /** @return BelongsTo<ExampleUser, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(ExampleUser::class, 'actor_id');
    }

    /** The event in plain words, for the timeline. */
    public function describe(): string
    {
        $details = $this->details ?? [];
        $note = is_string($details['note'] ?? null) ? ' — "'.$details['note'].'"' : '';

        return match ($this->event) {
            'submitted' => 'Submitted for approval',
            'step_reached' => 'Waiting for '.$this->step_name,
            'step_skipped' => $this->step_name.' skipped: its condition did not hold',
            'voted' => (($details['decision'] ?? null) === 'changes' ? 'Asked for changes' : 'Approved').(is_string($details['team'] ?? null) ? ' for '.((new PurchaseFlow)->teams()[$details['team']] ?? $details['team']) : '').$note,
            'outcome' => $this->step_name.': '.(is_string($details['label'] ?? null) ? $details['label'] : 'decided'),
            'returned' => 'Sent back to the requester',
            'finished' => 'Approved — every step is done',
            'stuck' => 'Stuck: nobody can act on '.$this->step_name,
            'retried' => 'Tried '.$this->step_name.' again',
            'reminded' => 'Reminder sent for '.$this->step_name,
            default => $this->event,
        };
    }
}
