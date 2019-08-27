<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\AudioClip;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;

class AudioClipPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user)
    {
        return $user->canDoAction('audio-clips.read');
    }
    
    public function create(User $user)
    {
        return $this->userCanViewCompany($user, request()->company->id) 
            && $user->canDoAction('audio-clips.create');
    }

    public function read(User $user, AudioClip $audioClip)
    {
        return $this->resourceBelongsToCompany($audioClip->company_id)
            && $this->userCanViewCompany($user, $audioClip->company_id) 
            && $user->canDoAction('audio-clips.read');
    }

    public function update(User $user, AudioClip $audioClip)
    {
        return $this->resourceBelongsToCompany($audioClip->company_id)
            && $this->userCanViewCompany($user, $audioClip->company_id) 
            && $user->canDoAction('audio-clips.update');
    }

    public function delete(User $user, AudioClip $audioClip)
    {
        return $this->resourceBelongsToCompany($audioClip->company_id)
            && $this->userCanViewCompany($user, $audioClip->company_id) 
            && $user->canDoAction('audio-clips.delete');
    }
}
