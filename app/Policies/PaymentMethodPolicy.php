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
        return $user->canDoAction('payment-methods.create');
    }

    public function list(User $user)
    {
        return $user->canDoAction('payment-methods.read');
    }

    public function read(User $user, PaymentMethod $paymentMethod)
    {
        return $user->account_id == $paymentMethod->account_id 
            && $user->canDoAction('payment-methods.read');
    }

    public function update(User $user, PaymentMethod $paymentMethod)
    {
        return $user->account_id == $paymentMethod->account_id 
            && $user->canDoAction('payment-methods.update');
    }

    public function delete(User $user, PaymentMethod $paymentMethod)
    {
        return $user->account_id == $paymentMethod->account_id 
            && $user->canDoAction('payment-methods.delete');
    }
}
