<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Plugin;
use App\Models\Company\CompanyPlugin;

class CompanyPluginTest extends TestCase
{
    use \Tests\CreatesAccount;
    
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
                        $companyPlugin->toArray()
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
     * @group company-plugins--
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
}
