<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumberConfig;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;

class PhoneNumberConfigPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user)
    {
        return $user->canDoAction('phone-number-configs.read');
    }
    
    public function create(User $user)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('phone-number-configs.create');
    }

    public function read(User $user, PhoneNumberConfig $phoneNumberConfig)
    {
        return $this->resourceBelongsToCompany($phoneNumberConfig->company_id)
            && $this->userCanViewCompany($user, $phoneNumberConfig->company_id) 
            && $user->canDoAction('phone-number-configs.read');
    }

    public function update(User $user, PhoneNumberConfig $phoneNumberConfig)
    {
        return $this->resourceBelongsToCompany($phoneNumberConfig->company_id)
            && $this->userCanViewCompany($user, $phoneNumberConfig->company_id) 
            && $user->canDoAction('phone-number-configs.update');
    }

    public function delete(User $user, PhoneNumberConfig $phoneNumberConfig)
    {
        return $this->resourceBelongsToCompany($phoneNumberConfig->company_id)
            && $this->userCanViewCompany($user, $phoneNumberConfig->company_id) 
            && $user->canDoAction('phone-number-configs.delete');
    }
}
