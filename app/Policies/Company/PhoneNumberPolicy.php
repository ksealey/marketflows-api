<?php

namespace App\Policies\Company;

use App\Models\User;
use App\Models\Company;
use App\Models\Company\PhoneNumber;
use Illuminate\Auth\Access\HandlesAuthorization;
use App\Policies\Traits\HandlesCompanyResources;

class PhoneNumberPolicy
{
    use HandlesAuthorization, HandlesCompanyResources;

    public function list(User $user, Company $company)
    {
        return $user->canDoCompanyAction($company->id, 'phone-numbers.read');
    }
    
    public function create(User $user, Company $company)
    {
        return $user->canDoCompanyAction($company->id, 'phone-numbers.create');
    }

    public function read(User $user, PhoneNumber $phoneNumber, Company $company)
    {
        return $user->canDoCompanyAction($company->id, 'phone-numbers.read')
            && $company->id == $phoneNumber->company_id;
    }

    public function update(User $user, PhoneNumber $phoneNumber, Company $company)
    {
        return $user->canDoCompanyAction($company->id, 'phone-numbers.update')
            && $company->id == $phoneNumber->company_id;
    }

    public function delete(User $user, PhoneNumber $phoneNumber, Company $company)
    {
        return $user->canDoCompanyAction($company->id, 'phone-numbers.delete')
            && $company->id == $phoneNumber->company_id;
    }

    public function bulkDelete(User $user, Company $company)
    {
        return $user->canDoCompanyAction($company->id, 'phone-numbers.delete');
    }
}
