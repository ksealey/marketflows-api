<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function read(User $user, User $otherUser)
    {
        return $user->account_id == $otherUser->account_id 
            && ( $user->id == $otherUser->id || $user->canDoAction('users.read') );
    }

    public function update(User $user, User $otherUser)
    {
        return $user->account_id == $otherUser->account_id 
            && ( $user->id == $otherUser->id || $user->canDoAction('users.update') );
    }

    public function delete(User $user, User $otherUser)
    {
        return $user->account_id == $otherUser->account_id 
            && $user->id != $otherUser->id 
            && $user->canDoAction('users.delete');
    }
    
}
