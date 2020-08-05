<?php

namespace App\Policies\Company;

use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\User;
use App\Models\Company;
use App\Models\Company\Webhook;

class WebhookPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

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

    public function read(User $user, Webhook $webhook, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $webhook->company_id === $company->id;
    }

    public function update(User $user, Webhook $webhook, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $webhook->company_id === $company->id;
    }

    public function delete(User $user, Webhook $webhook, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $webhook->company_id === $company->id;
    }
}
