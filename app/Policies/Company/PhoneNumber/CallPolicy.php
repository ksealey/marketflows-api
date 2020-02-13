<?php

namespace App\Policies\Company\PhoneNumber;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumber\Call;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;

class CallPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user)
    {
       
    }

    public function read(User $user, PhoneNumber $phoneNumber, Call $call)
    {
        /*
        return $phoneNumber->id == $call->phone_number_id
            && $this->resourceBelongsToCompany($phoneNumber->company_id)
            && $this->userCanViewCompany($user, $phoneNumber->company_id) 
            && $user->canDoAction('phone-numbers.read');
        */
    }
}
