<?php

namespace App\Policies;

use App\Models\User;
use App\Models\BlockedPhoneNumber;
use Illuminate\Auth\Access\HandlesAuthorization;

class BlockedPhoneNumberPolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->canDoAction('blocked-phone-numbers.create');
    }

    public function list(User $user)
    {
        return $user->canDoAction('blocked-phone-numbers.read');
    }

    public function read(User $user, BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $user->account_id == $blockedPhoneNumber->account_id 
            && $user->canDoAction('blocked-phone-numbers.read');
    }

    public function update(User $user, BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $user->account_id == $blockedPhoneNumber->account_id 
            && $user->canDoAction('blocked-phone-numbers.update');
    }

    public function delete(User $user, BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $user->account_id == $blockedPhoneNumber->account_id 
            && $user->canDoAction('blocked-phone-numbers.delete');
    }
}
