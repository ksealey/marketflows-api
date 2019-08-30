<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\Campaign;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;


class CampaignPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user)
    {
        return $user->canDoAction('campaigns.read');
    }
    
    public function create(User $user)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('campaigns.create');
    }

    public function read(User $user, Campaign $campaign)
    {
        return $this->resourceBelongsToCompany($campaign->company_id)
            && $this->userCanViewCompany($user, $campaign->company_id) 
            && $user->canDoAction('campaigns.read');
    }

    public function update(User $user, Campaign $campaign)
    {
        return $this->resourceBelongsToCompany($campaign->company_id)
            && $this->userCanViewCompany($user, $campaign->company_id) 
            && $user->canDoAction('campaigns.update');
    }

    public function delete(User $user, Campaign $campaign)
    {
        return $this->resourceBelongsToCompany($campaign->company_id)
            && $this->userCanViewCompany($user, $campaign->company_id) 
            && $user->canDoAction('campaigns.delete');
    }
}
