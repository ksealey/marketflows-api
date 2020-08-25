<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Company\Contact;
use App\Models\Company\PhoneNumber;

class ContactController extends Controller
{
    public static $fields = [
        'contacts.first_name',
        'contacts.last_name',
        'contacts.email',
        'contacts.phone',
        'contacts.city',
        'contacts.state',
        'contacts.zip',
        'contacts.country',
        'contacts.created_at',
        'contacts.updated_at'
    ];

    /**
     * List contacts
     * 
     */
    public function list(Request $request, Company $company)
    {
        //  Build Query
        $query = Contact::where('company_id', $company->id);

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
            'email'      => 'bail|required_without:phone|email|max:128',
            'phone'      => 'bail|required_without:email|digits_between:10,13',
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
        $query = Contact::where('company_id', $company->id);
        if( $request->email && ! $request->phone ){
            $query->where('email', $request->email);
        }elseif( ! $request->email && $request->phone ){
            $query->where('phone', $request->phone);
        }elseif( $request->email && $request->phone ){
            $query->where('email', $request->email)
                    ->orWhere('phone', $request->phone);
        }

        $contact = $query->first();
        if( $contact ){
            $matchField = $contact->email === $request->email ? 'email' : '';
            if( ! $matchField ){
                $matchField = $contact->phone === $request->phone ? 'phone' : '';
            }

            return response([
                'error' => 'Duplicate exists matching field ' . $matchField
            ], 400);
        }
            
        $contact = Contact::create([
            'uuid'       => Str::uuid(),
            'account_id' => $company->account_id,
            'company_id' => $company->id,
            'first_name' => $request->first_name,
            'last_name'  => $request->last_name,
            'email'      => $request->email,
            'country_code' => PhoneNumber::countryCode($request->phone),
            'phone'        => PhoneNumber::number($request->phone),
            'city'       => $request->city,
            'state'      => $request->state,
            'zip'        => $request->zip,
            'country'    => $request->country,
            'created_by' => $request->user()->id
        ]);

        return response($contact, 201);
    } 

    /**
     * Read a contact
     * 
     */
    public function read(Request $request, Company $company, Contact $contact)
    {
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
            'email'      => 'bail|required_without:phone|email|max:128',
            'phone'      => 'bail|required_without:email|digits_between:10,13',
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
        $query = Contact::where('company_id', $company->id);
        if( $request->email && ! $request->phone ){
            $query->where('email', $request->email);
        }elseif( ! $request->email && $request->phone ){
            $query->where('phone', $request->phone);
        }elseif( $request->email && $request->phone ){
            $query->where('email', $request->email)
                    ->orWhere('phone', $request->phone);
        }
        
        $otherContact = $query->where('id', '!=', $contact->id)
                              ->first();

        if( $otherContact ){
            $matchField = $otherContact->email === $request->email ? 'email' : '';
            if( ! $matchField ){
                $matchField = $otherContact->phone === $request->phone ? 'phone' : '';
            }

            return response([
                'error' => 'Duplicate exists matching field ' . $matchField
            ], 400);
        }

        if( $request->has('first_name') )
            $contact->first_name = $request->first_name;
        if( $request->has('last_name') )
            $contact->last_name = $request->last_name;
        if( $request->has('email') )
            $contact->email = $request->email;
        if( $request->has('phone') ){
            $contact->country_code = PhoneNumber::countryCode($request->phone);
            $contact->phone        = PhoneNumber::number($request->phone);
        }
        if( $request->has('city') )
            $contact->city = $request->city;
        if( $request->has('state') )
            $contact->state = $request->state;
        if( $request->has('zip') )
            $contact->zip = $request->zip;
        if( $request->has('country') )
            $contact->country = $request->country;

        $contact->updated_by = $request->user()->id;
        
        $contact->save();

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
