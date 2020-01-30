<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\BlockedPhoneNumber;
use Validator;
use DB;

class BlockedPhoneNumberController extends Controller
{
    /**
     * List blocked phone numbers
     * 
     */
    public function list(Request $request, Company $company)
    {
        $rules = [
            'limit'     => 'numeric',
            'page'      => 'numeric',
            'order_by'  => 'in:name,number,created_at,updated_at,call_count',
            'order_dir' => 'in:asc,desc'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $limit      = intval($request->limit) ?: 250;
        $limit      = $limit > 250 ? 250 : $limit;
        $page       = intval($request->page)  ?: 1;
        $orderBy    = $request->order_by  ?: 'created_at';
        $orderDir   = strtoupper($request->order_dir) ?: 'DESC';
        $search     = $request->search;

        $query = BlockedPhoneNumber::select(['*', DB::raw('(SELECT COUNT(*) FROM calls WHERE calls.blocked_phone_number_id = blocked_phone_numbers.id) AS call_count')])
                                    ->where('account_id', $company->account_id)
                                    ->where('company_id', $company->id);
        
        if( $search ){
            $query->where(function($query) use($search){
                $query->where('name', 'like', '%' . $search . '%')
                      ->orWhere('number', 'like', '%' . $search . '%');
            });
        }

        $resultCount = $query->count();
        $records     = $query->offset(( $page - 1 ) * $limit)
                             ->limit($limit)
                             ->orderBy($orderBy, $orderDir)
                             ->get();

        $records = $this->withAppendedDates($company, $records);

        $nextPage = null;
        if( $resultCount > ($page * $limit) )
            $nextPage = $page + 1;

        return response([
            'results'              => $records,
            'result_count'         => $resultCount,
            'limit'                => $limit,
            'page'                 => $page,
            'total_pages'          => ceil($resultCount / $limit),
            'next_page'            => $nextPage
        ]);
    }

    /**
     * Create a blocked phone number
     * 
     */
    public function create(Request $request, Company $company)
    {
        $user = $request->user();

        $validator = Validator::make($request->input(), [
            'number'        => 'bail|required|numeric|digits_between:10,13',
            'name'          => 'bail|required|max:255',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $number = BlockedPhoneNumber::create([
            'account_id' => $user->account_id,
            'company_id' => $company->id,
            'created_by' => $user->id,
            'name'       => $request->name,
            'number'     => $request->number
        ]);

        return response($number, 201);
    }

    /**
     * View a blocked number
     * 
     */
    public function read(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $blockedPhoneNumber->calls;

        return response($blockedPhoneNumber);
    }

    /**
     * Update a blocked phone number
     * 
     */
    public function update(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $validator = Validator::make($request->input(), [
            'name'          => 'bail|required|max:255',
        ]);

        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $blockedPhoneNumber->name = $request->name;

        $blockedPhoneNumber->save();

        return response($blockedPhoneNumber);
    }

    /**
     * Delete a blocked phone number
     * 
     */
    public function delete(Request $request, Company $company, BlockedPhoneNumber $blockedPhoneNumber)
    {
        $blockedPhoneNumber->delete();

        return response([
            'message' => 'Deleted.'
        ]);
    }
}
