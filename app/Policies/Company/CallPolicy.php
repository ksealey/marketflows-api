<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use Illuminate\Auth\Access\HandlesAuthorization;

class CallPolicy
{
    use HandlesAuthorization;

    public function list(User $user, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company);
    }


    public function read(User $user, Call $call, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $company->id === $call->company_id;
    }

    public function delete(User $user, Call $call, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $company->id === $call->company_id;
    }
}
