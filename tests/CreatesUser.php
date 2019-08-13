<?php
namespace Tests;

trait CreatesUser
{
    public $company;

    public $user;

    public function createUser(array $fields = [])
    {
        $this->company =  factory(\App\Models\Company::class)->create();
        
        $this->user =  factory(\App\Models\User::class)->create(array_merge([
            'company_id' => $this->company->id
        ], $fields));

        return $this->user;
    }

    public function authHeaders(array $additionalHeaders = [])
    {
        return array_merge([
            'Authorization' => 'Bearer ' . base64_encode($this->user->getBearerToken())
        ], $additionalHeaders);
    }
}