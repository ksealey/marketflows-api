<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\AudioClip;
use Illuminate\Auth\Access\HandlesAuthorization;

class AudioClipPolicy
{
    use HandlesAuthorization;

    public function list(User $user)
    {
        return $user->canDoAction('read');
    }
    
    public function create(User $user, Company $company)
    {
        return $user->canDoAction('create')
            && $user->canViewCompany($company);
               
    }

    public function read(User $user, AudioClip $audioClip, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $audioClip->company_id === $company->id;
    }

    public function update(User $user, AudioClip $audioClip, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company)
            && $audioClip->company_id === $company->id;
    }

    public function delete(User $user, AudioClip $audioClip, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $audioClip->company_id === $company->id;
    }
}
