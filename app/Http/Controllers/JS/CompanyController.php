<?php

namespace App\Http\Controllers\JS;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Company;

class CompanyController extends Controller
{
    /**
     * Render company specific JS
     * 
     */
    public function js(Request $request, $companyId)
    {
        $company = Company::find($companyId);

        if( ! $company )
            return response('');

        return view('js.company', [
            'company' => $company,
        ], [
            'Content-Type' => 'application/javascript'
        ]);
    }
}
