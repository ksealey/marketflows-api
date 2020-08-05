<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use Illuminate\Auth\Access\HandlesAuthorization;

class CallPolicy
{
    use HandlesAuthorization;

    public function list(User $user)
    {
        return $this->userCanViewCompany($user)
            && $user->canDoAction('calls.read');
    }

    public function read(User $user, Call $call)
    {
        return $this->resourceBelongsToCompany($call->company_id)
            && $this->userCanViewCompany($user) 
            && $user->canDoAction('calls.read');
    }
}
