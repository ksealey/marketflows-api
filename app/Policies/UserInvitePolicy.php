<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserInvitePolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->canDoAction('user-invites.create');
    }

    public function read(User $user, UserInvite $userInvite)
    {
        return $user->id === $userInvite->created_by
                && $user->canDoAction('user-invites.read');
    }

    public function delete(User $user, UserInvite $userInvite)
    {
        return $user->id === $userInvite->created_by
                && $user->canDoAction('user-invites.delete');
    }
}
