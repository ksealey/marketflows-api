<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumberConfig;
use Illuminate\Auth\Access\HandlesAuthorization;

class PhoneNumberConfigPolicy
{
    use HandlesAuthorization;

    public function list(User $user, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company);
    }
    
    public function create(User $user, Company $company)
    {
        return $user->canDoAction('create')
            && $user->canViewCompany($company);
    }

    public function read(User $user, PhoneNumberConfig $phoneNumberConfig, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $phoneNumberConfig->company_id === $company->id;
    }

    public function update(User $user, PhoneNumberConfig $phoneNumberConfig, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company)
            && $phoneNumberConfig->company_id === $company->id;
    }

    public function clone(User $user, PhoneNumberConfig $phoneNumberConfig, Company $company)
    {
        return $user->canDoAction('create')
            && $user->canViewCompany($company)
            && $phoneNumberConfig->company_id === $company->id;
    }

    public function delete(User $user, PhoneNumberConfig $phoneNumberConfig, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $phoneNumberConfig->company_id === $company->id;
    }
}
