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
        return $user->canDoAction('companies.read');
    }

    public function create(User $user)
    {
        return $user->canDoAction('companies.create');
    }

    public function read(User $user, Company $company)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('companies.read');
    }

    public function update(User $user, Company $company)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('companies.update');
    }

    public function delete(User $user, Company $company)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('companies.delete');
    }

    public function bulkDelete(User $user)
    {
        return $user->canDoAction('companies.delete');
    }
}
