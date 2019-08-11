<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Property;
use Validator;
use Exception;

class PropertyController extends Controller
{
    protected $rules = [
        'name'   => 'required|max:200',
        'domain' => 'required',
    ];

    public function create(Request $request)
    {
        $validator = Validator::make($request->input(), $this->rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 400);
        }

        $user = $request->user();
        $domain = Property::domain($request->domain);
        if( ! $domain )     
            return response([
                'error' => 'Domain invalid',
                'ok'     => false
            ], 400);

        $property = Property::create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name'       => $request->name,
            'domain'     => Property::domain($request->domain),
            'key'        => 'MKF-' . str_random(16)
        ]);

        return response([
            'message'  => 'created',
            'ok'       => true,
            'property' => $property,
        ], 201);
    }

    /**
     * Read a record
     * 
     */
    public function read(Request $request, Property $property)
    {
        $user = $request->user();
        
        if( ! $property || $property->company_id != $user->company_id ){
            return response([
                'error' => 'Not found',
                'ok'    => false
            ], 404);
        }

        return response([
            'message' => 'success',
            'ok'      => true,
            'property'=> $property
        ]);
    }

    /**
     * Update a record
     * 
     */
    public function update(Request $request, Property $property)
    {
        $user = $request->user();
        if( ! $property || $property->company_id != $user->company_id ){
            return response([
                'error' => 'Not found',
                'ok'    => false
            ], 404);
        }

        $validator = Validator::make($request->input(), $this->rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 400);
        }

        $domain = Property::domain($request->domain);
        if( ! $domain )     
            return response([
                'error' => 'Domain invalid',
                'ok'     => false
            ], 400);

        $property->name   = $request->name;
        $property->domain = Property::domain($request->domain);
        $property->save();

        return response([
            'message' => 'updated',
            'ok'      => true,
            'property'=> $property
        ]);
    }

    public function delete(Request $request, Property $property)
    {
        $user = $request->user();
        if( ! $property || $property->company_id != $user->company_id ){
            return response([
                'error' => 'Not found',
                'ok'    => false
            ], 404);
        }

        $property->delete();

        return response([
            'message' => 'deleted',
            'ok'      => true,
            'property'=> $property
        ]);
    }

    /**
     * Get a list of all properties
     * 
     */
    public function list(Request $request)
    {
        return response([
            'message'    => 'success',
            'ok'         => true,
            'properties' => Property::where('company_id', $request->user()->company_id)
                                    ->get()
        ]);
    }
}
