<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\Alert;

class AlertPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function delete(User $user, Alert $alert)
    {
        return $alert->account_id == $user->account_id 
            && $alert->user_id === $user->id;
    }
}
