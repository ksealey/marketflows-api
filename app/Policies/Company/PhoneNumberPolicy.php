<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;

class PhoneNumberPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user)
    {
        return $this->userCanViewCompany($user, request()->company->id)
            && $user->canDoAction('phone-numbers.read');
    }
    
    public function create(User $user)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('phone-numbers.create');
    }

    public function read(User $user, PhoneNumber $phoneNumber)
    {
        return $this->resourceBelongsToCompany($phoneNumber->company_id)
            && $this->userCanViewCompany($user, $phoneNumber->company_id) 
            && $user->canDoAction('phone-numbers.read');
    }

    public function update(User $user, PhoneNumber $phoneNumber)
    {
        return $this->resourceBelongsToCompany($phoneNumber->company_id)
            && $this->userCanViewCompany($user, $phoneNumber->company_id) 
            && $user->canDoAction('phone-numbers.update');
    }

    public function delete(User $user, PhoneNumber $phoneNumber)
    {
        return $this->resourceBelongsToCompany($phoneNumber->company_id)
            && $this->userCanViewCompany($user, $phoneNumber->company_id) 
            && $user->canDoAction('phone-numbers.delete');
    }
}
