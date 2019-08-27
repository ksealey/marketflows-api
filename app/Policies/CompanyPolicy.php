<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Company;
use Illuminate\Auth\Access\HandlesAuthorization;

class CompanyPolicy
{
    use HandlesAuthorization;

    public function list(User $user)
    {
        return $user->canDoAction('companies.read');
    }

    public function create(User $user)
    {
        return $user->canDoAction('companies.create');
    }

    public function read(User $user, Company $company)
    {
        return $user->account_id == $company->account_id 
            && $user->canDoAction('companies.read');
    }

    public function update(User $user, Company $company)
    {
        return $user->account_id == $company->account_id 
            && $user->canDoAction('companies.update');
    }

    public function delete(User $user, Company $company)
    {
        return $user->account_id == $company->account_id 
            && $user->canDoAction('companies.delete');
    }
}
