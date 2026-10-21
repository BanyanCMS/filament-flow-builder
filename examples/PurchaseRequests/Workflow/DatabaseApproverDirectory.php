<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow;

use Illuminate\Support\Facades\DB;

/** The example's directory: an approver_assignments table (key, user). */
class DatabaseApproverDirectory implements ApproverDirectory
{
    public function userIds(string $key): array
    {
        $ids = DB::table('approver_assignments')->where('approver_key', $key)->orderBy('user_id')->pluck('user_id')->all();

        return array_values(array_map('intval', array_filter($ids, 'is_numeric')));
    }

    public function keysOf(int $userId): array
    {
        return array_values(array_filter(DB::table('approver_assignments')->where('user_id', $userId)->orderBy('approver_key')->pluck('approver_key')->all(), 'is_string'));
    }

    /** Give a user a key (used by the seeder and tests). */
    public function assign(string $key, int $userId): void
    {
        DB::table('approver_assignments')->insertOrIgnore(['approver_key' => $key, 'user_id' => $userId]);
    }
}
