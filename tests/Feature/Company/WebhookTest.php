<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Services\WebhookService;
use \App\Models\Company\Webhook;

class WebhookTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test listing
     * 
     * @group webhooks
     */
    public function testListing()
    {
        $company = $this->createCompany();
        factory(Webhook::class, 3)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'action'     => Webhook::ACTION_CALL_START
        ]);

        factory(Webhook::class, 3)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'action'     => Webhook::ACTION_CALL_END
        ]);

        $response = $this->json('GET', route('list-webhooks', [
            'company' => $company->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'result_count' => 6
        ]);

        $response->assertJSONStructure([
            "result_count",
            "results" => [
                'call_start' => [
                    [
                        'company_id',
                        'action',
                        'method',
                        'url',
                        'created_by',
                        'updated_by'
                    ]
                ]
            ]
        ]);
    }

    /**
     * Test create
     * 
     * @group webhooks
     */
    public function testCreateWebhook()
    {
        $company = $this->createCompany();

        $webhook  = factory(Webhook::class)->make();

        $this->mock(WebhookService::class,  function($mock) use($webhook){
            $mock->shouldReceive('sendWebhook')
                 ->with($webhook->method, $webhook->url, [
                     'message' => 'Hello from MarketFlows'
                 ])
                 ->andReturn((object)[
                     'ok'          => true,
                     'status_code' => 200,
                     'error'       => null
                 ]);
        });

        $response = $this->json('POST', route('create-webhook',[
            'company' => $company->id
        ]), [
            'action'     => $webhook->action,
            'method'     => $webhook->method,
            'url'        => $webhook->url        
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'company_id' => $company->id,
            'action'     => $webhook->action,
            'method'     => $webhook->method,
            'url'        => $webhook->url,
            'created_by' => $this->user->id
        ]);
    }

    /**
     * Test read
     * 
     * @group webhooks
     */
    public function testRead()
    {
        $company = $this->createCompany();
        $webhook = factory(Webhook::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('read-webhook', [
            'company' => $company->id,
            'webhook' => $webhook->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'company_id' => $company->id,
            'action'     => $webhook->action,
            'method'     => $webhook->method,
            'url'        => $webhook->url,
            'created_by' => $this->user->id,
            'updated_by' => null
        ]);
    }

    /**
     * Test update
     * 
     * @group webhooks
     */
    public function testUpdate()
    {
        $company = $this->createCompany();
        $webhook = factory(Webhook::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $updatedWebhook = factory(Webhook::class)->make();
        $this->mock(WebhookService::class,  function($mock) use($updatedWebhook){
            $mock->shouldReceive('sendWebhook')
                 ->with($updatedWebhook->method, $updatedWebhook->url, [
                     'message' => 'Hello from MarketFlows'
                 ])
                 ->andReturn((object)[
                     'ok'          => true,
                     'status_code' => 200,
                     'error'       => null
                 ]);
        });

        $response = $this->json('PUT', route('read-webhook', [
            'company' => $company->id,
            'webhook' => $webhook->id
        ]), [
            'action'     => $updatedWebhook->action,
            'method'     => $updatedWebhook->method,
            'url'        => $updatedWebhook->url
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'company_id' => $company->id,
            'action'     => $updatedWebhook->action,
            'method'     => $updatedWebhook->method,
            'url'        => $updatedWebhook->url,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id        
        ]);
    }

    /**
     * Test delete
     * 
     * @group webhooks
     */
    public function testDelete()
    {
        $company = $this->createCompany();
        $webhook = factory(Webhook::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $updatedWebhook = factory(Webhook::class)->make();

        $response = $this->json('DELETE', route('delete-webhook', [
            'company' => $company->id,
            'webhook' => $webhook->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'message' => 'Deleted'
        ]);

        $this->assertDatabaseMissing('webhooks', [
            'id' => $webhook->id,
            'deleted_at' => null
        ]);

        $this->assertDatabaseHas('webhooks', [
            'id'         => $webhook->id,
            'deleted_by' => $this->user->id
        ]);

    }
}
