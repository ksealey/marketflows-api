<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company\Report;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;

class ReportPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function list(User $user)
    {
        return $user->canDoAction('reports.read');
    }
    
    public function create(User $user)
    {
        return $this->userCanViewCompany($user)
            && $user->canDoAction('reports.create');
    }

    public function read(User $user, Report $report)
    {
        return $this->resourceBelongsToCompany($report->company_id)
            && $this->userCanViewCompany($user) 
            && $user->canDoAction('reports.read');
    }

    public function update(User $user, Report $report)
    {
        return $this->resourceBelongsToCompany($report->company_id)
            && $this->userCanViewCompany($user) 
            && $user->canDoAction('reports.update');
    }

    public function delete(User $user, Report $report)
    {
        return $this->resourceBelongsToCompany($report->company_id)
            && $this->userCanViewCompany($user) 
            && $user->canDoAction('reports.delete');
    }
}
