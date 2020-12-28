<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Company\Call;
use App\Models\Company\Contact;
use App\Models\Company\PhoneNumber;
use DB;

class ContactController extends Controller
{
    public static $fields = [
        'contacts.id',
        'contacts.first_name',
        'contacts.last_name',
        'contacts.email',
        'contacts.country_code',
        'contacts.number',
        'contacts.city',
        'contacts.state',
        'contacts.zip',
        'contacts.country',
        'contacts.lead_status',
        'contacts.last_lead_status',
        'contacts.created_at',
        'contacts.updated_at',
        'contact_call_count.call_count',
        'contact_last_call_at.last_call_at',
    ];

    /**
     * List contacts
     * 
     */
    public function list(Request $request, Company $company)
    {
        //  Build Query
        $query = Contact::select([
                            'contacts.*',
                            'contact_call_count.call_count',
                            'contact_last_call_at.last_call_at'
                        ])
                        ->leftJoin('contact_call_count', 'contact_call_count.contact_id', 'contacts.id')
                        ->leftJoin('contact_last_call_at', 'contact_last_call_at.contact_id', 'contacts.id')
                        ->where('contacts.company_id', $company->id);

        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            [],
            self::$fields,
            'contacts.created_at'
        );
    }

    /**
     * Create a contact
     * 
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'first_name' => 'bail|max:32',
            'last_name'  => 'bail|max:32',
            'email'      => ['bail', 'nullable', 'email', 'max:128', Rule::unique('contacts')->where(function ($query) use($company){
                return $query->where('company_id', $contact->company_id);
            })],
            'number'     => 'bail|required|digits_between:10,13',
            'city'       => 'bail|max:64',
            'state'      => 'bail|max:64',
            'zip'        => 'bail|max:16',
            'country'    => 'bail|max:64',
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }

        //
        //  Make sure no contact exists with this phone
        //
        $countryCode = PhoneNumber::countryCode($request->number);
        if( ! $countryCode )
            $countryCode = $company->country_code;
            
        $number = PhoneNumber::number($request->number);
        $query  = Contact::where('company_id', $company->id)
                        ->where(function($query) use($countryCode, $number){
                            $query->where('number', $number);
                            if( $countryCode ){
                                $query->where('country_code', $countryCode);
                            }
                        });

        $contact = $query->first();
        if( $contact ){
            return response([
                'error' => 'Duplicate contact exists with phone number'
            ], 400);
        }
            
        $contact = Contact::create([
            'uuid'       => Str::uuid(),
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'create_method' => Contact::CREATE_METHOD_MANUAL,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'country_code' => $countryCode,
            'number'       => $number,
            'city'       => $request->city,
            'state'      => $request->state,
            'zip'        => $request->zip,
            'country'    => $request->country,
            'created_by' => $request->user()->id,
        ]);

        return response($contact, 201);
    } 

    /**
     * Read a contact
     * 
     */
    public function read(Request $request, Company $company, Contact $contact)
    {
        $contact->activity = $contact->activity;

        return response($contact);
    }

    /**
     * Update a contact
     * 
     * 
     */
    public function update(Request $request, Company $company, Contact $contact)
    {
        $rules = [
            'first_name' => 'bail|max:32',
            'last_name'  => 'bail|max:32',
            'email'      => ['bail', 'nullable', 'email', 'max:128', Rule::unique('contacts')->where(function ($query) use($contact){
                return $query->where('company_id', $contact->company_id)
                             ->where('id', '!=', $contact->id);
            })],
            'lead_status'=> 'bail|nullable|in:Qualified,Unqualified,Customer,Potential,Unknown'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validator->errors()->first()
            ], 400);
        }
        
        if( $request->filled('first_name') )
            $contact->first_name = $request->first_name;

        if( $request->filled('last_name') )
            $contact->last_name = $request->last_name;

        if( $request->has('email') ){
            $contact->email = $request->email ?: null;
        }
        
        if( $request->has('lead_status') ){
            if( $contact->lead_status )
                $contact->last_lead_status = $contact->lead_status;
            $contact->lead_status = $request->lead_status;
        }

        $contact->updated_by = $request->user()->id;
        $contact->save();

        $contact->activity = $contact->activity;

        return response($contact);
    }

    /**
     * Delete a contact
     * 
     */
    public function delete(Request $request, Company $company, Contact $contact)
    {
        $contact->deleted_by = $request->user()->id;
        $contact->deleted_at = now();
        $contact->save();

        Call::where('contact_id', $contact->id)->delete();

        return response([
            'message' => 'Deleted'
        ]);
    }

    /**
     * Export contacts
     * 
     */
    public function export(Request $request, Company $company)
    {
        $request->merge([
            'company_id'   => $company->id,
            'company_name' => $company->name
        ]);
        
        return parent::exportResults(
            Contact::class,
            $request,
            [],
            self::$fields,
            'contacts.created_at'
        );
    }   
}
