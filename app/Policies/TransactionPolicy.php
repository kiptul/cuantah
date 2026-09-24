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
}
