<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Company;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;

class CompanyPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user)
    {
        return $user->canDoAction('read');
    }

    public function create(User $user)
    {
        return $user->canDoAction('create');
    }

    public function read(User $user, Company $company)
    {
        return $user->canDoAction('read') &&
               $user->canViewCompany($company);
    }

    public function update(User $user, Company $company)
    {
        return $user->canDoAction('update') &&
               $user->canViewCompany($company);
    }

    public function delete(User $user, Company $company)
    {
        return $user->canDoAction('delete') &&
               $user->canViewCompany($company);
    }

    public function bulkDelete(User $user)
    {
        return $user->canDoAction('delete');
    }
}
