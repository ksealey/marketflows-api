<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use Illuminate\Auth\Access\HandlesAuthorization;

class PhoneNumberPolicy
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
            && $user->email_verified_at;
    }

    public function read(User $user, PhoneNumber $phoneNumber, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $phoneNumber->company_id === $company->id;
    }

    public function update(User $user, PhoneNumber $phoneNumber, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company)
            && $phoneNumber->company_id === $company->id
            && $user->email_verified_at;
    }

    public function delete(User $user, PhoneNumber $phoneNumber, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $phoneNumber->company_id === $company->id;
    }
}
