<?php

namespace App\Policies;

use App\Models\User;
use App\Models\PaymentMethod;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentMethodPolicy
{
    use HandlesAuthorization;

    public function create(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function list(User $user)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function read(User $user, PaymentMethod $paymentMethod)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function update(User $user, PaymentMethod $paymentMethod)
    {
        return $user->role === User::ROLE_ADMIN;
    }

    public function delete(User $user, PaymentMethod $paymentMethod)
    {
        return $user->role === User::ROLE_ADMIN;
    }
}
