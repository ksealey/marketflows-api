<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->canDoAction('roles.create');
    }

    public function read(User $user, Role $role)
    {
        return $user->account_id == $role->account_id 
            && $user->canDoAction('roles.read');
    }

    public function update(User $user, Role $role)
    {
        return $user->account_id == $role->account_id 
            && $user->canDoAction('roles.update');
    }

    public function delete(User $user, Role $role)
    {
        return $user->account_id == $role->account_id 
            && $user->canDoAction('roles.delete');
    }
}
