<?php

namespace App\Http\Controllers\Company;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Company\WebSourceField;
use Illuminate\Validation\Rule;
use Validator;

class WebSourceFieldController extends Controller
{
    /**
     * List Web Source Fields
     * 
     */
    public function list(Request $request, Company $company)
    {
        $webSourceFields = WebSourceField::where('company_id', $company->id)
                                         ->get();

        return response([
            'web_source_fields' => $webSourceFields
        ]);
    }

    /**
     * Create a new Web Source Field
     * 
     */
    public function create(Request $request, Company $company)
    {
        $rules = [
            'label' => [
                'bail',
                'required',
                'max:255',
                 Rule::unique('web_source_fields')->where(function ($query) use($request, $company) {
                    return $query->where('company_id', $company->id)
                                 ->where('label', $request->label);
                }),
            ],
            'url_parameter' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'direct_value'  => 'nullable|string|max:255',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $webSourceField = WebSourceField::create([
            'company_id'    => $company->id,
            'label'         => $request->label,
            'url_parameter' => $request->url_parameter ?: null,
            'default_value' => $request->default_value ?: null,
            'direct_value'  => $request->direct_value  ?: null,
        ]);

        return response([
            'web_source_field' => $webSourceField
        ], 201);
    }

    /**
     * Read an existing Web Source Field
     * 
     */
    public function read(Request $request, Company $company, WebSourceField $webSourceField)
    {
        return response([
            'web_source_field' => $webSourceField
        ]);
    }

    /**
     * Update an existing Web Source Field
     * 
     */
    public function update(Request $request, Company $company, WebSourceField $webSourceField)
    {
        $rules = [
            'label' => [
                'bail',
                'required',
                'max:255',
                 Rule::unique('web_source_fields')->where(function ($query) use($request, $company, $webSourceField) {
                    return $query->where('company_id', $company->id)
                                 ->where('label', $request->label)
                                 ->where('id', '!=', $webSourceField->id);
                }),
            ],
            'url_parameter' => 'nullable|string|max:255',
            'default_value' => 'nullable|string|max:255',
            'direct_value'  => 'nullable|string|max:255',
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $webSourceField->label         = $request->label;
        $webSourceField->url_parameter = $request->url_parameter ?: null;
        $webSourceField->default_value = $request->default_value ?: null;
        $webSourceField->direct_value  = $request->direct_value  ?: null;
        $webSourceField->save();

        return response([
            'web_source_field' => $webSourceField
        ]);
    }

    /**
     * Delete an existing Web Source Field
     * 
     */
    public function delete(Request $request, Company $company, WebSourceField $webSourceField)
    {
        $webSourceField->delete();

        return response([
            'message' => 'deleted'
        ]);
    }
}
