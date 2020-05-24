<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumberPool;
use Illuminate\Auth\Access\HandlesAuthorization;

class PhoneNumberPoolPolicy
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
            && $user->canViewCompany($company)
            && $user->email_verified_at;;
    }

    public function read(User $user, PhoneNumberPool $phoneNumberPool, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $phoneNumberPool->company_id === $company->id;
    }

    public function update(User $user, PhoneNumberPool $phoneNumberPool, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company)
            && $phoneNumberPool->company_id === $company->id
            && $user->email_verified_at;;
    }

    public function delete(User $user, PhoneNumberPool $phoneNumberPool, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $phoneNumberPool->company_id === $company->id;
    }
}
