<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Property;
use Validator;
use Exception;

class PropertyController extends Controller
{
    public function create(Request $request)
    {
        $rules = [
            'name'   => 'required|max:200',
            'domain' => 'required|url',
        ];

        $messages = [
            'name.required'     => 'Name required',
            'name.max'          => 'Name cannot exceed 200 characters',
            'domain.required'   => 'Domain required',
            'domain.url'        => 'Domain must be a URL'
        ];

        $validator = $this->validator($request->input());

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first(),
                'ok'    => false
            ], 400);
        }

        $user = $request->user();

        $property = Property::create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'name'       => $request->name,
            'domain'     => $this->domain($request->domain),
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
    public function read(Request $request, $id)
    {
        $property = Property::find($id);
        $user     = $request->user();
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
    public function update(Request $request, $id)
    {
        $property = Property::find($id);
        $user     = $request->user();
        if( ! $property || $property->company_id != $user->company_id ){
            return response([
                'error' => 'Not found',
                'ok'    => false
            ], 404);
        }

        $validator = $this->validator($request->input());
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $property->name   = $request->name;
        $property->domain = $this->domain($request->domain);
        $property->save();

        return response([
            'message' => 'updated',
            'ok'      => true,
            'property'=> $property
        ]);
    }

    public function delete(Request $request, $id)
    {
        $property = Property::find($id);
        $user     = $request->user();
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

    /**
     * Create a validator
     * 
     */
    public function validator(array $input = [])
    {
        $rules = [
            'name'   => 'required|max:200',
            'domain' => 'required|max:1024|regex:/(.*)+\.([0-9A-z]{2,16})/',
        ];

        $messages = [
            'name.required'     => 'Name required',
            'name.max'          => 'Name cannot exceed 200 characters',
            'domain.required'   => 'Domain required',
            'domain.max'        => 'Domain cannot exceed 1024 characters',
            'domain.regex'      => 'Domain invalid'
        ];

        return Validator::make($input, $rules, $messages);
    }

    public function domain($url)
    {
        return Property::domain($url);
    }
}
