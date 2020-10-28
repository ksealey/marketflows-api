<?php

namespace App\Policies\Company;

use Illuminate\Auth\Access\HandlesAuthorization;
use \App\Models\User;
use \App\Models\Company;
use \App\Models\Company\CompanyPlugin;

class CompanyPluginPolicy
{
    use HandlesAuthorization;

    public function list(User $user, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company);
    }
    
    public function install(User $user, Company $company)
    {
        return $user->canDoAction('create')
            && $user->canViewCompany($company);
    }

    public function read(User $user, CompanyPlugin $companyPlugin, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $company->id === $companyPlugin->company_id;
    }

    public function update(User $user, CompanyPlugin $companyPlugin, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company)
            && $company->id === $companyPlugin->company_id;
    }

    public function uninstall(User $user, CompanyPlugin $companyPlugin, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $company->id === $companyPlugin->company_id;
    }
}
