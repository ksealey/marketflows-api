<?php

namespace Tests\Feature\Company;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Company\Contact;
use App\Services\ExportService;
use App\Models\Company\PhoneNumber;
use Queue;

class ContactTest extends TestCase
{
    use \Tests\CreatesAccount;

    /**
     * Test listing contacts
     * 
     * @group contacts
     */
    public function testList()
    {
        $company = $this->createCompany();

        $contact = factory(Contact::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('list-contacts', [
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
            'results' => [
                [
                    'id',
                    'first_name',
                    'last_name',
                    'country_code',
                    'number',
                    'kind',
                    'link'
                ]
            ]
        ]);
    }

    /**
     * Test listing contacts with a date range
     * 
     * @group contacts
     */
    public function testListWithDateRange()
    {
        $company    = $this->createCompany();
        $twoDaysAgo = now()->subDays(2);

        factory(Contact::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $contacts = factory(Contact::class, 2)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'created_at' => $twoDaysAgo,
        ]);

        $twoDaysAgo->setTimeZone(new \DateTimeZone($this->user->timezone));
        $response = $this->json('GET', route('list-contacts', [
            'company' => $company->id
        ]), [
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d'),
            'date_type' => 'CUSTOM',
            'order_by'  => 'contacts.id',
            'order_dir' => 'asc'
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 2,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
        ]);
        $response->assertJSON([
            'results' => [
                [
                    'id'            => $contacts[0]['id'],
                    'first_name'    => $contacts[0]['first_name'],
                    'last_name'     => $contacts[0]['last_name'],
                    'number'        => $contacts[0]['number'],
                    'kind'          => $contacts[0]['kind'],
                    'link'          => $contacts[0]['link']
                ],
                [
                    'id'            => $contacts[1]['id'],
                    'first_name'    => $contacts[1]['first_name'],
                    'last_name'     => $contacts[1]['last_name'],
                    'number'        => $contacts[1]['number'],
                    'kind'          => $contacts[1]['kind'],
                    'link'          => $contacts[1]['link']
                ]
            ]
        ]);
    }

    /**
     * Test listing contacts with filter
     * 
     * @group contacts
     */
    public function testListWithFilter()
    {
        $company  = $this->createCompany();
        $contacts = factory(Contact::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $contact  = $contacts->first();
        $response = $this->json('GET', route('list-contacts', [
            'company' => $company->id
        ]), [
            'date_type' => 'ALL_TIME',
            'conditions' => json_encode([
                [
                    [
                        'field' => 'contacts.first_name',
                        'operator' => 'EQUALS',
                        'inputs' => [ $contact->first_name ]
                    ],
                    [
                        'field' => 'contacts.last_name',
                        'operator' => 'EQUALS',
                        'inputs' => [ $contact->last_name ]
                    ],
                    [
                        'field' => 'contacts.number',
                        'operator' => 'EQUALS',
                        'inputs' => [ $contact->number ]
                    ]
                ]
            ])
        ]);

        $response->assertStatus(200);
        $response->assertJSON([
            "result_count" => 1,
            "limit"        => 250,
            "page"         => 1,
            "total_pages"  => 1,
            "next_page"    => null,
        ]);

        $response->assertJSON([
            'results' => [
                [
                    'id'            => $contact->id,
                    'first_name'    => $contact->first_name,
                    'last_name'     => $contact->last_name,
                    'country_code'  => $contact->country_code,
                    'number'        => $contact->number,
                    'kind'          => $contact->kind,
                    'link'          => $contact->link
                ]
            ]
        ]);
    }

    /**
     * Test exporting contacts
     * 
     * @group contacts
     */
    public function testExportContacts()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });

        $company  = $this->createCompany();
        $contacts = factory(Contact::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('export-contacts', [
            'company' => $company->id
        ]));

        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test exporting contacts with conditions
     * 
     * @group contacts
     */
    public function testExportContactsWithConditions()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });
        
        $company     = $this->createCompany();
        $contacts = factory(Contact::class, 10)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);
        $contact = $contacts->first();
        $conditions = [
            [
                [
                    'field'    => 'contacts.first_name',
                    'operator' => 'IN',
                    'inputs'   => [
                        $contact->first_name
                    ]
                ]
            ]
        ];

        $response = $this->json('GET', route('export-contacts', [
            'company' => $company->id
        ]), [
            'conditions' => json_encode($conditions)
        ]);
        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test exporting contacts with date ranges
     * 
     * @group contacts
     */
    public function testExportContactsWithDateRanges()
    {
        $exportData  = bin2hex(random_bytes(200));
        $this->mock(ExportService::class, function($mock) use($exportData){
            $mock->shouldReceive('exportAsOutput')
                 ->once()
                 ->andReturn($exportData);
        });
        $twoDaysAgo = now()->subDays(2);

        $company    = $this->createCompany();
        $contact    = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'created_at' => $twoDaysAgo
        ]);
        factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $twoDaysAgo->setTimeZone(new \DateTimeZone($this->user->timezone));
        
        $response = $this->json('GET', route('export-contacts', [
            'company' => $company->id
        ]), [
            'start_date' => $twoDaysAgo->format('Y-m-d'),
            'end_date'   => $twoDaysAgo->format('Y-m-d')
        ]);

        $response->assertStatus(200);
        $response->assertSee($exportData);
    }

    /**
     * Test creating a contact
     * 
     * @group contacts
     */
    public function testCreateContact()
    {
        $company = $this->createCompany();
        $contact = factory(Contact::class)->make();
        $body       = [
            'first_name'    => $contact->first_name,
            'last_name'     => $contact->last_name,
            'number'        => $contact->fullPhone(),
            'city'          => $contact->city,
            'state'         => $contact->state,
            'zip'           => $contact->zip,
        ];

        $response = $this->json('POST', route('create-contact', [
            'company' => $company->id
        ]), $body);
        
        $response->assertStatus(201);
        $response->assertJSON([
            'first_name'    => $contact->first_name,
            'last_name'     => $contact->last_name,
            'country_code'  => $contact->country_code,
            'number'        => $contact->number,
            'city'          => $contact->city,
            'state'         => $contact->state,
            'zip'           => $contact->zip,
        ]);
        $this->assertDatabaseHas('contacts', [
            'company_id'   => $company->id,
            'country_code' => PhoneNumber::countryCode($contact->fullPhone()),
            'number'       => PhoneNumber::number($contact->fullPhone()),
            'created_by'   => $this->user->id,
        ]);
    }

    /**
     * Test creating a contact fails without phone
     * 
     * @group contacts
     */
    public function testCreateContactFailsForNoPhone()
    {
        $company = $this->createCompany();
        $contact = factory(Contact::class)->make([
            'number' => ''
        ]);
        $body       = [
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'number' => $contact->number,
            'city' => $contact->city,
            'state' => $contact->state,
            'zip' => $contact->zip,
        ];

        $response = $this->json('POST', route('create-contact', [
            'company' => $company->id
        ]), $body);
        
        $response->assertStatus(400);
        $response->assertJSONStructure([
            'error'
        ]);
    }

   
    /**
     * Test creating a contact fails when contact matching phone exists
     * 
     * @group contacts
     */
    public function testCreateContactFailsForExisingPhone()
    {
        $company      = $this->createCompany();
        $otherContact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $contact = factory(Contact::class)->make([
            'country_code' => $otherContact->country_code,
            'number'       => $otherContact->number
        ]);
        $body       = [
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'number' => $contact->fullPhone(),
        ];
        $response = $this->json('POST', route('create-contact', [
            'company' => $company->id
        ]), $body);
        
        $response->assertStatus(400);
        $response->assertJSON([
            'error' => 'Duplicate contact exists with phone number'
        ]);
    }

    /**
     * Test reading a contact
     * 
     * @group contacts
     */
    public function testReadContact()
    {
        $company = $this->createCompany();
        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('GET', route('read-contact', [
            'company' => $company->id,
            'contact' => $contact->id
        ]));

        $response->assertStatus(200);
        $response->assertJSON([
            'id'        => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'country_code' => $contact->country_code,
            'number'     => $contact->number,
            'kind'      => 'Contact',
            'link'      => route('read-contact', [
                'company' => $company->id,
                'contact' => $contact->id
            ])
        ]);
    }

     /**
     * Test updating a contact
     * 
     * @group contacts
     */
    public function testUpdateContact()
    {
        $company = $this->createCompany();
        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $updateData = factory(Contact::class)->make();
        $body       = [
            'first_name' => $updateData->first_name,
            'last_name' => $updateData->last_name,
        ];

        $response = $this->json('PUT', route('update-contact', [
            'company' => $company->id,
            'contact' => $contact->id
        ]), $body);

        $response->assertStatus(200);
        $response->assertJSON($body);
        $response->assertJSON([
            'activity' => []
        ]);

        $this->assertDatabaseHas('contacts', [
            'id'         => $contact->id,
            'updated_by' => $this->user->id
        ]);
    }

     /**
     * Test deleting a contact
     * 
     * @group contacts
     */
    public function testDeleteContact()
    {
        $company = $this->createCompany();
        $contact = factory(Contact::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        $response = $this->json('DELETE', route('delete-contact', [
            'company' => $company->id,
            'contact' => $contact->id
        ]));

        $response->assertStatus(200);

        $this->assertDatabaseMissing('contacts', [
            'id'         => $contact->id,
            'deleted_at' => null
        ]);
    }
}
