<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Charge;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChargePolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function list(User $user)
    {
        return $user->canDoAction('charges.read');
    }

    public function read(User $user, Charge $charge)
    {
        return $user->canDoAction('charges.read') && $charge->account_id == $user->account_id;
    }
}
