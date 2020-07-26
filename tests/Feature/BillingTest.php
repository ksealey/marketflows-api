<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use \Tests\CreatesAccount;
   /**
    *   Test fetching billing data 
    *   
    *   @group billing
    */
    public function testReadBilling()
    {
        $response = $this->json('GET', route('read-billing') );
        
        $response->assertStatus(200);

        $response->assertJSON([
            "monthly_fee"  => $this->account->monthly_fee,
            "primary_payment_method" => $this->billing->primary_payment_method,
            "past_due_amount" => $this->billing->past_due_amount,
            "bill_at"=> $this->billing->bill_at,
            "period_starts_at"=> $this->billing->period_starts_at,
            "period_ends_at"=> $this->billing->period_ends_at,
        ]);
    }
}
