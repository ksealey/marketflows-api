<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Plugin;
use App\Models\Company\CompanyPlugin;
use \App\Models\Company\Contact;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \App\Services\WebhookService;

class CompanyPluginTest extends TestCase
{
    use \Tests\CreatesAccount, WithFaker;
    
    /**
     * Test listing available plugins
     * 
     * @group company-plugins
     */
    public function testList()
    {
        CompanyPlugin::where('id', '>', 0)->delete();
        Plugin::where('id', '>', 0)->delete();

        //  Install a few
        $company    = $this->createCompany();
        $available  = factory(Plugin::class,2)->create();
        $installed  = factory(Plugin::class)->create();
        $companyPlugin = factory(CompanyPlugin::class)->create([
            'company_id' => $company->id,
            'plugin_key' => $installed->key,
            'enabled_at' => now()->format('Y-m-d H:i:s')
        ]);
        
        $response = $this->json('GET', route('list-plugins', [
            'company' => $company->id,
        ]));
        $response->assertJSON([
            'results' => [
                'available' => [
                    'total'   => 2,
                    'results' => $available->toArray()
                ],
                'installed' => [
                    'total' => 1,
                    'results' => [
                        [
                            'id'         => $companyPlugin->id,
                            'name'       => $installed->name,
                            'plugin_key' => $installed->key
                        ]
                    ]
                ]
            ]
        ]);
        $response->assertStatus(200);
    }

    /**
     * Test listing available plugins with available search
     * 
     * @group company-plugins
     */
    public function testListWithSearch()
    {
        CompanyPlugin::where('id', '>', 0)->delete();
        Plugin::where('id', '>', 0)->delete();

        //  Install a few
        $company    = $this->createCompany();
        $available  = factory(Plugin::class, 5)->create();
        $installed  = factory(Plugin::class, 5)->create()->each(function($plugin) use($company){
            factory(CompanyPlugin::class)->create([
                'company_id' => $company->id,
                'plugin_key' => $plugin->key,
                'enabled_at' => now()->format('Y-m-d H:i:s')
            ]);
        });
        
        $response = $this->json('GET', route('list-plugins', [
            'company'          => $company->id,
            
        ]), [
            'available_search' => $available->first()->name,
            'installed_search' => $installed->first()->name
        ]);
        $response->assertJSON([
            'results' => [
                'available' => [
                    'total'   => 1,
                    'results' => [
                        [
                            'key' => $available->first()->key
                        ]
                    ]
                ],
                'installed' => [
                    'total' => 1,
                    'results' => [
                        [
                            'plugin_key' => $installed->first()->key
                        ]
                    ]
                ]
            ]
        ]);
        $response->assertStatus(200);
    }

    /**
     * Test install
     * 
     * @group company-plugins
     */
    public function testInstall()
    {
        CompanyPlugin::where('id', '>', 0)->delete();
        Plugin::where('id', '>', 0)->delete();

        //  Install a few
        $company = $this->createCompany();
        $plugin  = factory(Plugin::class, 5)->create()->first();

        $response = $this->json('POST', route('install-plugin', [
            'company' => $company->id,
        ]), [
           'plugin_key' => $plugin->key,
           'settings'   => json_encode([])
        ]);
        $response->assertJSON([
            'plugin_key' => $plugin->key,
            'name'       => $plugin->name,
            'company_id' => $company->id,
            'enabled_at' => null,
            'settings'   => []
        ]);
        $response->assertStatus(201);

        $this->assertDatabaseHas('company_plugins', [
            'plugin_key' => $plugin->key,
            'company_id' => $company->id,
        ]);
    }

    /**
     * Test 2nd install attempt fails
     * 
     * @group company-plugins
     */
    public function testSecondInstallAttemptFails()
    {
        CompanyPlugin::where('id', '>', 0)->delete();
        Plugin::where('id', '>', 0)->delete();

        $company = $this->createCompany();
        $plugin  = factory(Plugin::class, 5)->create()->first();
        factory(CompanyPlugin::class)->create([
            'company_id' => $company->id,
            'plugin_key' => $plugin->key,
            'enabled_at' => now()->format('Y-m-d H:i:s')
        ]);

        $response = $this->json('POST', route('install-plugin', [
            'company' => $company->id,
        ]), [
           'plugin_key' => $plugin->key,
           'settings'   => json_encode([])
        ]);
        $response->assertJSONStructure([
            'error'
        ]);
        $response->assertStatus(400);
    }

    /**
     * Test update
     * 
     * @group company-plugins
     */
    public function testUpdate()
    {
        CompanyPlugin::where('id', '>', 0)->delete();
        Plugin::where('id', '>', 0)->delete();

        $company = $this->createCompany();
        $plugin  = factory(Plugin::class, 5)->create()->first();
        $companyPlugin = factory(CompanyPlugin::class)->create([
            'company_id' => $company->id,
            'plugin_key' => $plugin->key,
            'enabled_at' => null
        ]);

        $settings = [
            'foo' => str_random(),
            'bar' => str_random(),
        ];
        $response = $this->json('PUT', route('update-plugin', [
            'company'       => $company->id,
            'companyPlugin' => $companyPlugin->id,
        ]), [
           'settings'   => json_encode($settings)
        ]);
        $response->assertJSON([
            'plugin_key' => $plugin->key,
            'name'       => $plugin->name,
            'company_id' => $company->id,
            'enabled_at' => null,
            'settings'   => $settings
        ]);
        $response->assertStatus(200);
    }

    /**
     * Test uninstall
     * 
     * @group company-plugins
     */
    public function testUninstall()
    {
        CompanyPlugin::where('id', '>', 0)->delete();
        Plugin::where('id', '>', 0)->delete();

        $company = $this->createCompany();
        $plugin  = factory(Plugin::class, 5)->create()->first();
        $companyPlugin = factory(CompanyPlugin::class)->create([
            'company_id' => $company->id,
            'plugin_key' => $plugin->key,
            'enabled_at' => null
        ]);

        $response = $this->json('DELETE', route('uninstall-plugin', [
            'company'       => $company->id,
            'companyPlugin' => $companyPlugin->id,
        ]));

        $response->assertJSON([
           'message' => 'Uninstalled'
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseMissing('company_plugins', [
            'id' => $companyPlugin->id
        ]);
    }

    /**
     * Test webhook plugin
     * 
     * @group company-plugins
     */
    public function testWebhookPlugin()
    {
        //
        //  Setup and install plugin
        //
        CompanyPlugin::where('id', '>', 0)->delete();
        Plugin::where('id', '>', 0)->delete();

        $company = $this->createCompany();
        $plugin = factory(Plugin::class)->create([
            'key' => 'webhooks'
        ]);

        $settings = (object)[
            'webhooks' => [
                Plugin::EVENT_CALL_START => (object)[
                    'method'=> 'POST',
                    'url'   => $this->faker()->url
                ],
                Plugin::EVENT_CALL_END => (object)[
                    'method'=> 'POST',
                    'url'   => $this->faker()->url
                ]
            ]
        ];

        $companyPlugin = factory(CompanyPlugin::class)->create([
            'company_id' => $company->id,
            'plugin_key' => $plugin->key,
            'enabled_at' => now(),
            'settings'   => json_encode($settings)
        ]);

        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'      => false,
            'greeting_enabled'       => false,
            'keypress_enabled'       => false,
            'whisper_enabled'        => false
        ]);

        $phoneNumber = $this->createPhoneNumber($company, $config);

        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ]);

        //
        //  Setup expectations
        //
        $this->mock(WebhookService::class,  function($mock){
            $mock->shouldReceive('sendWebhook')
                    ->times(2)
                    ->andReturn((object)[
                        'ok'          => true,
                        'status_code' => 200,
                        'error'       => null
                    ]);
        });

        //  
        //  Make call
        //
        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        //
        //  Complete call
        //
        $response = $this->json('POST', route('incoming-call-duration', $incomingCall->toArray()));
    }

    /**
     * Test webhook plugin
     * 
     * @group company-plugins
     */
    public function testAnalyticsPlugin()
    {
        //
        //  Setup and install plugin
        //
        CompanyPlugin::where('id', '>', 0)->delete();
        Plugin::where('id', '>', 0)->delete();

        $company = $this->createCompany();
        $plugin = factory(Plugin::class)->create([
            'key' => 'google_analytics'
        ]);

        $settings = (object)[
            'ga_id' => 'UA-' . mt_rand(111111,999999) . '-' . mt_rand(1,8)
        ];

        $companyPlugin = factory(CompanyPlugin::class)->create([
            'company_id' => $company->id,
            'plugin_key' => $plugin->key,
            'enabled_at' => now(),
            'settings'   => json_encode($settings)
        ]);

        $audioClip   = $this->createAudioClip($company);
        $config      = $this->createConfig($company, [
            'recording_enabled'      => false,
            'greeting_enabled'       => false,
            'keypress_enabled'       => false,
            'whisper_enabled'        => false
        ]);

        $phoneNumber = $this->createPhoneNumber($company, $config);

        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ]);

        //
        //  Setup expectations
        //
        $this->mock('Analytics',  function($mock) use($settings, $contact, $phoneNumber){
            $mock->shouldReceive('setProtocolVersion')
                    ->with('1')
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setTrackingId')
                    ->with($settings->ga_id)
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setUserId')
                    ->with($contact->uuid)
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setEventCategory')
                    ->with('call')
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setEventAction')
                    ->with('called')
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setEventLabel')
                    ->with($contact->e164PhoneFormat())
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setEventValue')
                    ->with(1)
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setAnonymizeIp')
                    ->with(1)
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setGeographicalOverride')
                    ->with($contact->country)
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setCampaignName')
                    ->with($phoneNumber->campaign)
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setCampaignContent')
                    ->with($phoneNumber->content)
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setCampaignSource')
                    ->with($phoneNumber->source)
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('setCampaignMedium')
                    ->with($phoneNumber->medium)
                    ->once()
                    ->andReturn($mock);

            $mock->shouldReceive('sendEvent')
                    ->once()
                    ->andReturn($mock);
        });

        //  
        //  Make call
        //
        $incomingCall = factory('Tests\Models\TwilioIncomingCall')->make([
            'From' => $contact->e164PhoneFormat(),
            'To' => $phoneNumber->e164Format()
        ]);
        $response = $this->json('POST', route('incoming-call'), $incomingCall->toArray());

        //
        //  Complete call
        //
        $response = $this->json('POST', route('incoming-call-duration', $incomingCall->toArray()));
    }
}
