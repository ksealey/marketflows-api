<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BillingPolicy
{
    use HandlesAuthorization;

    public function read($user)
    {
        return $user->role === User::ROLE_ADMIN;
    }
}
