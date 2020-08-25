<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function list(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function create(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function read(User $user, User $otherUser)
    {
        return $user->role === User::ROLE_ADMIN 
            && $user->account_id === $otherUser->account_id;
    }

    public function update(User $user, User $otherUser)
    {
        return $user->role === User::ROLE_ADMIN 
            && $user->account_id === $otherUser->account_id
            && $user->id != $otherUser->id;
    }

    public function delete(User $user, User $otherUser)
    {
        return $user->role === User::ROLE_ADMIN 
            && $user->account_id === $otherUser->account_id;
    }
    
}
