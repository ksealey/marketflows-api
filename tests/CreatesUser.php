<?php
namespace Tests;

trait CreatesUser
{
    public $company;

    public $user;

    public function createUser()
    {
        $this->company =  factory(\App\Models\Company::class)->create();
        
        $this->user =  factory(\App\Models\User::class)->create([
            'company_id' => $this->company->id
        ]);

        return $this->user;
    }

    public function authHeaders(array $additionalHeaders = [])
    {
        return array_merge([
            'Authorization' => 'Bearer ' . base64_encode($this->user->getBearerToken())
        ], $additionalHeaders);
    }
}