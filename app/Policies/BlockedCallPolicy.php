<?php

namespace App\Policies;

use App\Models\User;
use App\Models\BlockedPhoneNumber;
use Illuminate\Auth\Access\HandlesAuthorization;

class BlockedCallPolicy
{
    use HandlesAuthorization;

    
    public function list(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }
}
