<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
{
    use HandlesAuthorization;

    public function read(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function update(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function upgrade(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function summary(User $user)
    {
        return $user->role === User::ROLE_ADMIN || $user->role === User::ROLE_SYSTEM;
    }

    public function delete(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }
}
