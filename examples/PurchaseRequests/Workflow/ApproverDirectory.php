<?php

declare(strict_types=1);

namespace Banyan\FilamentFlowBuilder\Examples\PurchaseRequests\Workflow;

/**
 * The bridge to YOUR users: who holds an approver or team key of the
 * definition. Bind your own implementation (Spatie roles, an HR system, LDAP
 * groups, …); the workflow never asks anything else about people.
 */
interface ApproverDirectory
{
    /** @return list<int> user ids holding this approver or team key */
    public function userIds(string $key): array;

    /** @return list<string> the keys this user holds */
    public function keysOf(int $userId): array;
}
