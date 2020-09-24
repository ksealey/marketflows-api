<?php

namespace Tests;

use \App\Models\Account;
use \App\Models\Billing;
use \App\Models\User;
use App\Models\PaymentMethod;
use App\Models\BillingStatement;
use App\Models\BillingStatementItem;
use \App\Models\AccountBlockedPhoneNumber;
use \App\Models\AccountBlockedPhoneNumber\AccountBlockedCall;
use App\Models\Company;
use App\Models\Company\Contact;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Report;
use App\Models\Company\PhoneNumberConfig;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use App\Models\Company\CallRecording;
use App\Models\Company\Transcription;
use \App\Models\Company\BlockedPhoneNumber;
use \App\Models\Company\BlockedPhoneNumber\BlockedCall;
use Storage;

trait CreatesAccount
{
    public $account;
    public $billing;
    public $user;
    public $paymentMethod;

    public function setUp() : void
    {
        parent::setUp();
        
        $this->account = factory(Account::class)->create();

        $this->billing = factory(Billing::class)->create([
            'account_id' => $this->account->id
        ]);

        $this->user = factory(User::class)->create([
            'account_id' => $this->account->id,
            'email_verified_at' => now()
        ]);

        $this->paymentMethod = factory(PaymentMethod::class)->create([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ]);
    }

    public function createAccount()
    {
        return factory(Account::class)->create();
    }

    public function createCompany($with = [])
    {
        return factory(Company::class)->create(array_merge([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ], $with));
    }

    public function createPaymentMethod()
    {
        return factory(PaymentMethod::class)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ]);
    }

    public function createAudioClip($company, $with = [])
    {
        return factory(AudioClip::class)->create(array_merge([
            'created_by' => $this->user->id,
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ], $with));
    }

    public function createConfig($company, $with = [])
    {
        return factory(PhoneNumberConfig::class)->create(array_merge([
            'created_by' => $this->user->id,
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ], $with));
    }

    public function createContact($company, $with = [])
    {
        return factory(Contact::class)->create(array_merge([
            'created_by' => $this->user->id,
            'account_id' => $company->account_id,
            'company_id' => $company->id
        ], $with));
    }

    public function createCall($company, $with = [])
    {
        return factory(Call::class)->create(array_merge([
            'account_id'      => $company->account_id,
            'company_id'      => $company->id
        ], $with));
    }

    public function createPhoneNumber($company, $config, $with = [])
    {
        return factory(PhoneNumber::class)->create(array_merge([
            'created_by' => $this->user->id,
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'phone_number_config_id' => $config->id,
            'swap_rules' => $this->makeSwapRules()
        ], $with));
    }

    public function createCompanies()
    {
        Storage::fake();
        
        //  Company
        $company = factory(Company::class)->create([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ]);

        //  Audio Clip
        $audioClip  = factory(AudioClip::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        Storage::put($audioClip->path, 'foobar');

        //  Config
        $config = factory(PhoneNumberConfig::class)->create([
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id,
            'greeting_audio_clip_id' => $audioClip->id,
        ]);

        //  Some numbers
        $phoneNumber = factory(PhoneNumber::class)->create([
            'account_id' => $this->user->account_id,
            'company_id' => $company->id,
            'created_by' => $this->user->id, 
            'phone_number_config_id' => $config->id
        ]);

        factory(Contact::class, 1)->create([
            'account_id'      => $company->account_id,
            'company_id'      => $company->id
        ])->each(function($contact) use($phoneNumber){
            factory(Call::class, 1)->create([
                'contact_id' => $contact->id,
                'account_id' => $phoneNumber->account_id,
                'company_id' => $phoneNumber->company_id,
                'phone_number_id' =>$phoneNumber->id,
                'phone_number_name' => $phoneNumber->name,
            ])->each(function($call){
                $path = '/to-recording/' . str_random(10) . '.mp3';
                Storage::put($path, 'foobar');
                factory(CallRecording::class)->create([
                    'call_id' => $call->id,
                    'path'     => $path
                ]);
                factory(CallRecording::class)->create([
                    'call_id' => $call->id,
                    'path'    => $path
                ]);
            });
        });
        

        //  Blocked Numbers (Company)
        factory(BlockedPhoneNumber::class, 2)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ])->each(function($blockedNumber) use($phoneNumber){
            factory(BlockedCall::class, 2)->create([
                'blocked_phone_number_id' => $blockedNumber->id,
                'phone_number_id'         => $phoneNumber->id,
            ]);
        });

         //  Blocked Numbers (Account)
         factory(AccountBlockedPhoneNumber::class, 2)->create([
            'account_id' => $this->account->id,
            'created_by' => $this->user->id
        ])->each(function($accountBlockedNumber) use($phoneNumber){
            factory(AccountBlockedCall::class, 2)->create([
                'account_blocked_phone_number_id' => $accountBlockedNumber->id,
                'phone_number_id'                 => $phoneNumber->id,
            ]);
        });

        //  Report
        $report = factory(Report::class)->create([
            'account_id' => $this->account->id,
            'company_id' => $company->id,
            'created_by' => $this->user->id
        ]);

        return [
            'company' => $company,
            'audio_clip' => $audioClip,
            'phone_number_config' => $config,
            'phone_number' => $phoneNumber->first(),
            'report' => $report
        ];
    }

    public function populateUsage($companyCount = 2, $localNumberCount = 2, $tollFreeNumberCount = 2, $localCallsPerNumber = 10, $tollFreeCallsPerNumber = 10)
    {
        Storage::fake();
        
        //  Company
        factory(Company::class, $companyCount)->create([
            'account_id' => $this->user->account_id,
            'created_by' => $this->user->id
        ])->each(function($company) use($localNumberCount, $tollFreeNumberCount, $localCallsPerNumber, $tollFreeCallsPerNumber){
            //  Audio Clip
            $audioClip  = factory(AudioClip::class)->create([
                'account_id' => $company->account_id,
                'company_id' => $company->id,
                'created_by' => $this->user->id
            ]);

            Storage::put($audioClip->path, 'foobar');

            //  Config
            $config = factory(PhoneNumberConfig::class)->create([
                'account_id' => $company->account_id,
                'company_id' => $company->id,
                'created_by' => $this->user->id,
                'greeting_audio_clip_id' => $audioClip->id,
            ]);

            //  Add local numbers with calls
            factory(PhoneNumber::class, $localNumberCount)->create([
                'account_id' => $this->user->account_id,
                'company_id' => $company->id,
                'created_by' => $this->user->id, 
                'phone_number_config_id' => $config->id,
                'type'       => 'Local'
            ])->each(function($phoneNumber) use($company, $localCallsPerNumber){
                factory(Contact::class, $localCallsPerNumber)->create([
                    'account_id'      => $company->account_id,
                    'company_id'      => $company->id
                ])->each(function($contact) use($phoneNumber){
                    factory(Call::class, 1)->create([
                        'contact_id'      => $contact->id,
                        'account_id'      => $phoneNumber->account_id,
                        'company_id'      => $phoneNumber->company_id,
                        'phone_number_id' => $phoneNumber->id,
                        'phone_number_name' => $phoneNumber->name,
                        'duration'        => mt_rand(0, 59),
                        'type'            => 'Local',
                        'created_at'      => now()->subMinutes(5)
                    ])->each(function($call){
                        $path = '/to-recording/' . str_random(10) . '.mp3';
                        Storage::put($path, 'foobar');

                        factory(CallRecording::class)->create([
                            'call_id' => $call->id,
                            'path'     => $path,
                            'file_size' => 1024 * 1024 * 10
                        ]);

                        factory(Transcription::class)->create([
                            'call_id'  => $call->id
                        ]);
                    });
                });
            });

            //  Add toll-free numbers with calls
            factory(PhoneNumber::class, $tollFreeNumberCount)->create([
                'account_id' => $this->user->account_id,
                'company_id' => $company->id,
                'created_by' => $this->user->id, 
                'phone_number_config_id' => $config->id,
                'type'       => 'Toll-Free'
            ])->each(function($phoneNumber) use($company, $tollFreeCallsPerNumber){
                factory(Contact::class, $tollFreeCallsPerNumber)->create([
                    'account_id'      => $company->account_id,
                    'company_id'      => $company->id
                ])->each(function($contact) use($phoneNumber){
                    factory(Call::class, 1)->create([
                        'contact_id'      => $contact->id,
                        'account_id'      => $phoneNumber->account_id,
                        'company_id'      => $phoneNumber->company_id,
                        'phone_number_id' => $phoneNumber->id,
                        'phone_number_name'=> $phoneNumber->name,
                        'duration'        => mt_rand(0, 59),
                        'type'            => 'Toll-Free',
                        'created_at'      => now()->subMinutes(5)
                    ])->each(function($call){
                        $path = '/to-recording/' . str_random(10) . '.mp3';
                        Storage::put($path, 'foobar');

                        factory(CallRecording::class)->create([
                            'call_id' => $call->id,
                            'path'     => $path,
                            'file_size' => 1024 * 1024 * 10
                        ]);

                        factory(Transcription::class)->create([
                            'call_id'  => $call->id
                        ]);
                    });
                });
            });
        });
    }

    public function createBillableStatement($with = [])
    {
        $statement = factory(BillingStatement::class)->create(array_merge([
            'billing_id' => $this->billing->id
        ], $with));

        for( $i = 0; $i < 4; $i++ ){
            factory(BillingStatementItem::class)->create([
                'billing_statement_id' => $statement->id
            ]);
        }

        return $statement;
    }

    public function makeSwapRules($with = [])
    {
        return json_encode(array_merge([
                'targets' => [
                    '18003098829'
                ],
                'device_types'  => ['ALL'],
                'browser_types' => ['ALL'],
                'inclusion_rules' => [
                    [
                        'rules' => [
                            [
                                'type' => 'ALL'
                            ]
                        ]
                    ]
                ],
                'exclusion_rules' => []
            ],  $with));
    }

    public function json($method, $route, $body = [], $headers = [])
    {
        $headers = array_merge([
            'Authorization' => 'Bearer ' . $this->user->auth_token
        ], $headers);

        return parent::json($method, $route, $body, $headers);
    }

    public function noAuthjson($method, $route, $body = [], $headers = [])
    {
        return parent::json($method, $route, $body, $headers);
    }

    public function createConditions(array $fields, $asString = false)
    {
        $conditionGroups = [];

        foreach( $fields as $key => $field ){
            $rand           = mt_rand(1, 10);
            $inputs         = [];
            switch($rand)
            {
                case 1:
                    $operator = 'EQUALS';
                    $inputs   = [
                        str_random(mt_rand(1, 64))
                    ];
                break;

                case 2:
                    $operator = 'NOT_EQUALS';
                    $inputs   = [
                        str_random(mt_rand(1, 64))
                    ];
                break;

                case 3:
                    $operator = 'IN';
                    $inputs   = [
                        str_random(mt_rand(1, 64)),
                        str_random(mt_rand(1, 64)),
                        str_random(mt_rand(1, 64)),
                    ];
                break;

                case 4:
                    $operator = 'NOT_IN';
                    $inputs   = [
                        str_random(mt_rand(1, 64)),
                        str_random(mt_rand(1, 64)),
                        str_random(mt_rand(1, 64)),
                    ];
                break;

                case 5:
                    $operator = 'EMPTY';
                break;

                case 6:
                    $operator = 'NOT_EMPTY';
                break;
                    
                case 7:
                    $operator = 'LIKE';
                    $inputs   = [
                        str_random(mt_rand(1, 64))
                    ];
                break;

                case 8:
                    $operator = 'NOT_LIKE';
                    $inputs   = [
                        str_random(mt_rand(1, 64))
                    ];
                break;

                case 9:
                    $operator = 'IS_TRUE';
                break;

                case 10:
                    $operator = 'IS_FALSE';
                break;
            }
            $conditionGroups[] = [
                [
                    'field'    => $field,
                    'operator' => $operator,
                    'inputs'   => $inputs
                ]
            ];
        }


        return $asString ? json_encode($conditionGroups) : $conditionGroups;
    }
}