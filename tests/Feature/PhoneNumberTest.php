<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Company\PhoneNumber;
use \App\Helpers\PhoneNumberManager;

class PhoneNumberTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test creating a local offline phone number
     * 
     * @group phone-numbers
     */
    public function testCreateOfflineLocalPhoneNumber()
    {
        $company    = $this->createCompany();
        $config     = factory(PhoneNumberConfig::class)->create([
            'created_by' => $this->user->id,
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ]);

        $numberData = factory(PhoneNumber::class)->make();
        $areaCode   = '813'; 
        
        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();
        $this->mock(PhoneNumberManager::class, function ($mock) use($areaCode, $company, $twilioNumber){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with($areaCode, 1, PhoneNumber::TYPE_LOCAL, $company->country)
                 ->andReturn(
                    [$twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->once()
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });

        //  Try creating a local phone number
        $response = $this->json('POST', route('create-phone-number', [
            'company' => $company->id
        ]), [
            'name'        => $numberData->name,
            'category'    => 'OFFLINE',
            'sub_category'=> 'RADIO',
            'type'        => PhoneNumber::TYPE_LOCAL,
            'starts_with' => $areaCode,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'phone_number_config_id' => $config->id,
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $numberData->name,
            'category'    => 'OFFLINE',
            'sub_category'=> 'RADIO',
            'type'        => PhoneNumber::TYPE_LOCAL,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'     => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'country_code' => PhoneNumber::countryCode($twilioNumber->phoneNumber),
            'number'       => PhoneNumber::number($twilioNumber->phoneNumber)
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'id' => $response['id']
        ]);
    }

     /**
     * Test creating a toll-free offline phone number
     * 
     * @group phone-numbers
     */
    public function testCreateOfflineTollFreePhoneNumber()
    {
        $company    = $this->createCompany();
        $config     = factory(PhoneNumberConfig::class)->create([
            'created_by' => $this->user->id,
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ]);

        $numberData   = factory(PhoneNumber::class)->make();        
        $twilioNumber = factory('Tests\Models\TwilioPhoneNumber')->make();
        $this->mock(PhoneNumberManager::class, function ($mock) use($company, $twilioNumber){
            $mock->shouldReceive('listAvailable')
                 ->once()
                 ->with('', 1, PhoneNumber::TYPE_TOLL_FREE, $company->country)
                 ->andReturn(
                    [$twilioNumber]
                );

            $mock->shouldReceive('purchase')
                ->once()
                ->with($twilioNumber->phoneNumber)
                ->andReturn(
                   $twilioNumber
               );
        });

        //  Try creating a local phone number
        $response = $this->json('POST', route('create-phone-number', [
            'company' => $company->id
        ]), [
            'name'        => $numberData->name,
            'category'    => 'OFFLINE',
            'sub_category'=> 'RADIO',
            'type'        => PhoneNumber::TYPE_TOLL_FREE,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'    => $numberData->campaign,
            'phone_number_config_id' => $config->id,
        ]);
        $response->assertStatus(201);
        $response->assertJSON([
            'account_id'  => $company->account_id,
            'company_id'  => $company->id,
            'name'        => $numberData->name,
            'category'    => 'OFFLINE',
            'sub_category'=> 'RADIO',
            'type'        => PhoneNumber::TYPE_TOLL_FREE,
            'source'      => $numberData->source,
            'medium'      => $numberData->medium,
            'content'     => $numberData->content,
            'campaign'     => $numberData->campaign,
            'phone_number_config_id' => $config->id,
            'country_code' => PhoneNumber::countryCode($twilioNumber->phoneNumber),
            'number'       => PhoneNumber::number($twilioNumber->phoneNumber)
        ]);

        $this->assertDatabaseHas('phone_numbers', [
            'company_id' => $company->id,
            'account_id' => $company->account_id,
            'id' => $response['id']
        ]);
    }
}
