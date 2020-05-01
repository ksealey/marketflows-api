<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company;

class CompanyTest extends TestCase
{
   use \Tests\CreatesAccount;

   /**
    *   Test creating a company 
    *
    *   @group companies
    */
    public function testCreatingCompany()
    {
        $companyData = factory(Company::class)->make();
        
        $response = $this->json('POST', route('create-company'), $companyData->toArray());
        $response->dump();
    }
}
