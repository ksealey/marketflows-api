<?php

namespace App\Policies;

use App\Models\User;
use App\Models\BlockedPhoneNumber;
use Illuminate\Auth\Access\HandlesAuthorization;

class BlockedPhoneNumberPolicy
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

    public function read(User $user, BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $user->role === User::ROLE_ADMIN 
            && $user->account_id == $blockedPhoneNumber->account_id;
    }

    public function update(User $user, BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $user->role === User::ROLE_ADMIN 
            && $user->account_id == $blockedPhoneNumber->account_id;
    }

    public function delete(User $user, BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $user->role === User::ROLE_ADMIN 
            && $user->account_id == $blockedPhoneNumber->account_id;
    }
}
