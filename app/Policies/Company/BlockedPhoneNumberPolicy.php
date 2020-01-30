<?php

namespace App\Policies\Company;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Models\BlockedPhoneNumber;

class BlockedPhoneNumberPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('blocked-phone-numbers.read');
    }
    
    public function create(User $user)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('blocked-phone-numbers.create');
    }

    public function read(User $user, BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $this->resourceBelongsToCompany($blockedPhoneNumber->company_id)
            && $this->userCanViewCompany($user, $campaign->company_id) 
            && $user->canDoAction('blocked-phone-numbers.read');
    }

    public function update(User $user, BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $this->resourceBelongsToCompany($blockedPhoneNumber->company_id)
            && $this->userCanViewCompany($user, $campaign->company_id) 
            && $user->canDoAction('blocked-phone-numbers.update');
    }

    public function delete(User $user,  BlockedPhoneNumber $blockedPhoneNumber)
    {
        return $this->resourceBelongsToCompany($blockedPhoneNumber->company_id)
            && $this->userCanViewCompany($user, $campaign->company_id) 
            && $user->canDoAction('blocked-phone-numbers.delete');
    }
}
