<?php

namespace App\Policies;

use App\Models\User;
use App\Models\BillingStatement;
use Illuminate\Auth\Access\HandlesAuthorization;

class BillingStatementPolicy
{
    use HandlesAuthorization;

    public function list(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function read(User $user, BillingStatement $billingStatement)
    {
        return $user->role === User::ROLE_ADMIN
            && $user->account_id === $billingStatement->account_id;
    }
}
