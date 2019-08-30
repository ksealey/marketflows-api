<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumberPool;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;

class PhoneNumberPoolPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user)
    {
        return $user->canDoAction('phone-number-pools.read');
    }
    
    public function create(User $user)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('phone-number-pools.create');
    }

    public function read(User $user, PhoneNumberPool $phoneNumberPool)
    {
        return $this->resourceBelongsToCompany($phoneNumberPool->company_id)
            && $this->userCanViewCompany($user, $phoneNumberPool->company_id) 
            && $user->canDoAction('phone-number-pools.read');
    }

    public function update(User $user, PhoneNumberPool $phoneNumberPool)
    {
        return $this->resourceBelongsToCompany($phoneNumberPool->company_id)
            && $this->userCanViewCompany($user, $phoneNumberPool->company_id) 
            && $user->canDoAction('phone-number-pools.update');
    }

    public function delete(User $user, PhoneNumberPool $phoneNumberPool)
    {
        return $this->resourceBelongsToCompany($phoneNumberPool->company_id)
            && $this->userCanViewCompany($user, $phoneNumberPool->company_id) 
            && $user->canDoAction('phone-number-pools.delete');
    }
}
