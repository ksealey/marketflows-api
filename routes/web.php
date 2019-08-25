<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('companies/{companyId}/js/main.js', function(Request $request, $companyId){
    $company = \App\Models\Company::find($companyId);

    return trim(strip_tags(view('js.main', ['company'=>$company])->render()));
});




