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

    public function read(User $user, KeywordTrackingPool $keywordTrackingPool, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $keywordTrackingPool->company_id === $company->id;
    }

    public function update(User $user, KeywordTrackingPool $keywordTrackingPool, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company)
            && $keywordTrackingPool->company_id === $company->id;
    }

    public function delete(User $user, KeywordTrackingPool $keywordTrackingPool, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $keywordTrackingPool->company_id === $company->id;
    }

    public function detach(User $user, KeywordTrackingPool $keywordTrackingPool, Company $company, PhoneNumber $phoneNumber)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $keywordTrackingPool->company_id === $company->id
            && $keywordTrackingPool->id === $phoneNumber->keyword_tracking_pool_id;
    }
}
