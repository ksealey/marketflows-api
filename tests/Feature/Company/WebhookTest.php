<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
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
        factory(Webhook::class, 10)->create([
            'company_id' => $company->id
        ]);

        $response = $this->json('GET', route('list-webhooks', [
            'company' => $company->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 10,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
        ]);

        $response->assertJSONStructure([
            "results" => [
                [
                    'company_id',
                    'action',
                    'method',
                    'url',
                    'params' => [

                    ]
                ]
            ]
        ]);
    }

    /**
     * Test listing with conditions
     * 
     * @group webhooks
     */
    public function testListingWithConditions()
    {
        $company = $this->createCompany();
        $webhooks = factory(Webhook::class, 10)->create([
            'company_id' => $company->id
        ]);
        $firstWebhook = $webhooks->first();

        $response = $this->json('GET', route('list-webhooks', [
            'company' => $company->id,
            'conditions' => json_encode([
                [
                    'field'    =>  'webhooks.url',
                    'operator' => 'EQUALS',
                    'inputs'   => [ $firstWebhook->url ]
                ]
            ])
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
        ]);

        $response->assertJSON([
            "results" => [
                [
                    'company_id' => $company->id,
                    'action'     => $firstWebhook->action,
                    'method'     => $firstWebhook->method,
                    'url'        => $firstWebhook->url
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
        $response = $this->json('POST', route('create-webhook',[
            'company' => $company->id
        ]), [
            'action'     => $webhook->action,
            'method'     => $webhook->method,
            'url'        => $webhook->url,
            'params'     => json_encode($webhook->params)
        ]);

        $response->assertStatus(201);
        $response->assertJSON([
            'company_id' => $company->id,
            'action'     => $webhook->action,
            'method'     => $webhook->method,
            'url'        => $webhook->url,
            'params'     => (array)$webhook->params
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
            'company_id' => $company->id
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
            'params'     => (array)$webhook->params
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
            'company_id' => $company->id
        ]);

        $updatedWebhook = factory(Webhook::class)->make();

        $response = $this->json('PUT', route('read-webhook', [
            'company' => $company->id,
            'webhook' => $webhook->id
        ]), [
            'action'     => $updatedWebhook->action,
            'method'     => $updatedWebhook->method,
            'url'        => $updatedWebhook->url,
            'params'     => json_encode($updatedWebhook->params)
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            'company_id' => $company->id,
            'action'     => $updatedWebhook->action,
            'method'     => $updatedWebhook->method,
            'url'        => $updatedWebhook->url,
            'params'     => (array)$updatedWebhook->params
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
            'company_id' => $company->id
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
            'id' => $webhook->id
        ]);
    }
}
