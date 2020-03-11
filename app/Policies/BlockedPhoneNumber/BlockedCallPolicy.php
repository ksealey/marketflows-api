<?php

namespace App\Policies\BlockedPhoneNumber;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BlockedCallPolicy
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

    public function list(User $user)
    {
        return $user->canDoAction('blocked-calls.read');
    }
}
