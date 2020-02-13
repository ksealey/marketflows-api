<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\Transaction;

class TransactionPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function list(User $user)
    {
        return $user->canDoAction('transactions.read');
    }

    public function read(User $user, Transaction $transaction)
    {
        return $user->canDoAction('transactions.read') && $transaction->account_id == $user->account_id;
    }
}
