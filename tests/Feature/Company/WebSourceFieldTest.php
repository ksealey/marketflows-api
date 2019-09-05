<?php

namespace Tests\Feature\Company;

use \Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \App\Models\Company\WebSourceField;

class WebSourceFieldTest extends TestCase
{
    use \Tests\CreatesUser;

    /**
     * Test listing resources
     * 
     * @group web-source-fields
     */
    public function testList()
    {
        $user = $this->createUser();

        $field1 = factory(WebSourceField::class)->create([
            'company_id' => $this->company->id
        ]);

        $field2 = factory(WebSourceField::class)->create([
            'company_id' => $this->company->id
        ]); 

        $response = $this->json('GET', '/v1/companies/' . $this->company->id . '/web-source-fields', [], $this->authHeaders());

        $response->assertStatus(200);

        $response->assertJSON([
            'web_source_fields' => [
                [
                    'label' => $field1->label,
                    'url_parameter' => $field1->url_parameter,
                    'default_value' => $field1->default_value,
                    'direct_value' => $field1->direct_value,
                ],
                [
                    'label' => $field2->label,
                    'url_parameter' => $field2->url_parameter,
                    'default_value' => $field2->default_value,
                    'direct_value' => $field2->direct_value,
                ]
            ]
        ]);
    }

    /**
     * Test creating
     * 
     * @group web-source-fields
     */
    public function testCreate()
    {
        $user = $this->createUser();

        $field = factory(WebSourceField::class)->make();

        $params = [
            'label'         => $field->label,
            'url_parameter' => $field->url_parameter,
            'default_value' => $field->default_value,
            'direct_value'  => $field->direct_value,
        ];
        
        $response = $this->json('POST', '/v1/companies/' . $this->company->id . '/web-source-fields', $params, $this->authHeaders());
        $response->assertStatus(201);
        $response->assertJSON([
            'web_source_field' => $params
        ]);
    }

    /**
     * Test creating fails with duplicate label
     * 
     * @group web-source-fields
     */
    public function testCreateFailsWithDuplicateLabel()
    {
        $user = $this->createUser();

        $exisingField = factory(WebSourceField::class)->create([
            'company_id' => $this->company->id
        ]);

        $field = factory(WebSourceField::class)->make();

        $params = [
            'label'         => $exisingField->label,
            'url_parameter' => $field->url_parameter,
            'default_value' => $field->default_value,
            'direct_value'  => $field->direct_value,
        ];
        
        $response = $this->json('POST', '/v1/companies/' . $this->company->id . '/web-source-fields', $params, $this->authHeaders());
        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

    /**
     * Test creating happens with duplicate label on other company
     * 
     * @group web-source-fields
     */
    public function testCreateHappensWithDuplicateLabelOnOtherCompany()
    {
        $otherUser = $this->createUser();

        $user = $this->createUser();

        $exisingField = factory(WebSourceField::class)->create([
            'company_id' => $otherUser->company_id
        ]);

        $field = factory(WebSourceField::class)->make();

        $params = [
            'label'         => $exisingField->label,
            'url_parameter' => $field->url_parameter,
            'default_value' => $field->default_value,
            'direct_value'  => $field->direct_value,
        ];
        
        $response = $this->json('POST', '/v1/companies/' . $this->company->id . '/web-source-fields', $params, $this->authHeaders());
        $response->assertStatus(201);
        $response->assertJSON([
            'web_source_field' => $params
        ]);
    }

    /**
     * Test read
     * 
     * @group web-source-fields
     */
    public function testRead()
    {
        $user = $this->createUser();

        $exisingField = factory(WebSourceField::class)->create([
            'company_id' => $this->company->id
        ]);
        
        $response = $this->json('GET', '/v1/companies/' . $this->company->id . '/web-source-fields/' . $exisingField->id, [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJSON([
            'web_source_field' => [
                'id'            => $exisingField->id,
                'label'         => $exisingField->label,
                'url_parameter' => $exisingField->url_parameter,
                'default_value' => $exisingField->default_value,
                'direct_value'  => $exisingField->direct_value,
            ]
        ]);
    }

    /**
     * Test update
     * 
     * @group web-source-fields
     */
    public function testUpdate()
    {
        $user = $this->createUser();

        $exisingField = factory(WebSourceField::class)->create([
            'company_id' => $this->company->id
        ]);

        $field = factory(WebSourceField::class)->make();

        $params = [
            'id'            => $exisingField->id,
            'label'         => $field->label,
            'url_parameter' => $field->url_parameter,
            'default_value' => $field->default_value,
            'direct_value'  => $exisingField->direct_value,
        ];
        
        $response = $this->json('PUT', '/v1/companies/' . $this->company->id . '/web-source-fields/' . $exisingField->id, $params, $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJSON([
            'web_source_field' => $params
        ]);
    }
}
