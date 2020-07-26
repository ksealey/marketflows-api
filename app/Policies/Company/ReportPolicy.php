<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\Report;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReportPolicy
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

    public function read(User $user, Report $report, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $report->company_id === $company->id;
    }

    public function update(User $user, Report $report, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company)
            && $report->company_id === $company->id;
    }

    public function delete(User $user, Report $report, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $report->company_id === $company->id;
    }
}
