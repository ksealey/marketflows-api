<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company\WebSourceField;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;

class WebSourceFieldPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user)
    {
        return $user->canDoAction('companies.read');
    }
    
    public function create(User $user)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('companies.update');
    }

    public function read(User $user, WebSourceField $webSourceField)
    {
        return $this->resourceBelongsToCompany($webSourceField->company_id)
            && $this->userCanViewCompany($user, $webSourceField->company_id) 
            && $user->canDoAction('companies.read');
    }

    public function update(User $user, WebSourceField $webSourceField)
    {
        return $this->resourceBelongsToCompany($webSourceField->company_id)
            && $this->userCanViewCompany($user, $webSourceField->company_id) 
            && $user->canDoAction('companies.update');
    }

    public function delete(User $user, WebSourceField $webSourceField)
    {
        return $this->resourceBelongsToCompany($webSourceField->company_id)
            && $this->userCanViewCompany($user, $webSourceField->company_id) 
            && $user->canDoAction('companies.update');
    }
}
