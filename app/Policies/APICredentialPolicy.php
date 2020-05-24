<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use \App\Models\User;
use \App\Models\APICredential;

class APICredentialPolicy
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

    public function delete(User $user, APICredential $apiCredential)
    {
        return $user->role === User::ROLE_ADMIN 
            && $user->id === $apiCredential->user_id;
    }
}
