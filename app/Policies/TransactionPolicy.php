<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return ($user->isStaff() && $user->canAccessPartnerId($transaction->partner_id))
            || $transaction->user_id === $user->id;
    }

    /**
     * Admin-only actions on a transaction of a partner the admin has access to.
     */
    public function manage(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin() && $user->canAccessPartnerId($transaction->partner_id);
    }

    /**
     * Progressing a transaction: the employee it is assigned to, or a managing admin.
     */
    public function handle(User $user, Transaction $transaction): bool
    {
        if ($this->manage($user, $transaction)) {
            return true;
        }

        return $user->isEmployee()
            && $transaction->pickup?->assigned_user_id === $user->id;
    }

    /**
     * Taking an unassigned pickup: any staff member of the transaction's partner.
     */
    public function claim(User $user, Transaction $transaction): bool
    {
        return $user->isStaff() && $user->canAccessPartnerId($transaction->partner_id);
    }
}
