<?php
namespace App\Policies\Traits;

use \App\Models\User;

trait HandlesCompanyResources
{
    public function userCanViewCompany(User $user, $companyId)
    {
        $userCompanyIds = array_column($user->companies->toArray(), 'id');

        return in_array($companyId, $userCompanyIds);
    }

    public function resourceBelongsToCompany($resourceCompanyId)
    {
        return request()->company->id === $resourceCompanyId;
    }
}