<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\KeywordTrackingPool;
use Illuminate\Auth\Access\HandlesAuthorization;

class KeywordTrackingPoolPolicy
{
    use HandlesAuthorization;
    
    public function create(User $user, Company $company)
    {
        return $user->canDoAction('create')
            && $user->canViewCompany($company);
    }

    public function read(User $user, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company);
    }

    public function update(User $user, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company);
    }

    public function delete(User $user, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company);
    }

    public function detach(User $user, Company $company, PhoneNumber $phoneNumber)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $phoneNumber->company_id === $company->id
            && $phoneNumber->keyword_tracking_pool_id != null;
    }
}
