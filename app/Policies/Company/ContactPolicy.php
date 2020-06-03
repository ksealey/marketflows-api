<?php

namespace App\Policies\Company;

use Illuminate\Auth\Access\HandlesAuthorization;
use \App\Models\User;
use \App\Models\Company;
use \App\Models\Company\Contact;

class ContactPolicy
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
            && $user->canViewCompany($company);
    }

    public function read(User $user, Contact $contact, Company $company)
    {
        return $user->canDoAction('read')
            && $user->canViewCompany($company)
            && $company->id === $contact->company_id;
    }

    public function update(User $user, Contact $contact, Company $company)
    {
        return $user->canDoAction('update')
            && $user->canViewCompany($company)
            && $company->id === $contact->company_id;
    }

    public function delete(User $user, Contact $contact, Company $company)
    {
        return $user->canDoAction('delete')
            && $user->canViewCompany($company)
            && $company->id === $contact->company_id;
    }
}
