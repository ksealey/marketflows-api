<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\ScheduledExport;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScheduledExportPolicy
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

    public function read(User $user, ScheduledExport $scheduledExport, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $scheduledExport->company_id === $company->id;
    }

    public function update(User $user, ScheduledExport $scheduledExport, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company)
            && $scheduledExport->company_id === $company->id;
    }

    public function delete(User $user, ScheduledExport $scheduledExport, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $scheduledExport->company_id === $company->id;
    }
}
