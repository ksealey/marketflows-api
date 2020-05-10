<?php

namespace App\Policies\Company\BlockedPhoneNumber;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\BlockedPhoneNumber;
use Illuminate\Auth\Access\HandlesAuthorization;

class BlockedCallPolicy
{
    use HandlesAuthorization;

    
    public function list(User $user, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $company->id === $blockedPhoneNumber->company_id;
    }
}
