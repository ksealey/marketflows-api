<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
{
    use HandlesAuthorization;

    public function read(User $user)
    {
        return $user->canDoAction('accounts.read');
    }

    public function update(User $user)
    {
        return $user->canDoAction('accounts.update');
    }
}
