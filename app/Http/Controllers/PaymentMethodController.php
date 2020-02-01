<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \App\Models\PaymentMethod;
use Validator;
use Exception;
use DB;

class PaymentMethodController extends Controller
{
    
    /**
     * List Payment Methods
     * 
     */
    public function list(Request $request)
    {
        $rules = [
            'limit'     => 'numeric',
            'page'      => 'numeric',
            'order_by'  => 'in:created_at,updated_at,last_4,brand,expiration',
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
        $orderBy    = $request->order_by  ?: 'id';
        $orderDir   = strtoupper($request->order_dir) ?: 'ASC';
        $search     = $request->search;
        
        $user    = $request->user();
        $account = $user->account;

        $query = PaymentMethod::where('account_id', $account->id);
        
        if( $search )
            $query->where(function($query) use($search){
                $query->where('last_4', 'like', '%' . $search . '%')
                      ->orWhere('brand', 'like', '%' . $search . '%');
            });

        $resultCount = $query->count();
        $records     = $query->offset(($page - 1) * $limit)
                             ->limit($limit)
                             ->orderBy($orderBy, $orderDir)
                             ->get();

        $records = $this->withAppendedDates($account->timezone, $records);

        $nextPage = null;
        if( $resultCount > ($page * $limit) )
            $nextPage = $page + 1;

        return response([
            'results'         => $records,
            'result_count'    => $resultCount,
            'limit'           => $limit,
            'page'            => intval($request->page),
            'total_pages'     => ceil($resultCount / $limit),
            'next_page'       => $nextPage
        ]);
    }

    /**
     * Create a record
     * 
     * @param Request $request
     * @return Response
     */
    public function create(Request $request)
    {
        $rules = [ 
            'token'          => 'required',
            'primary_method' => 'boolean'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        try{
            $paymentMethod = PaymentMethod::createFromToken(
                $request->token, 
                $request->user(), 
                $request->primary_method
            );
        }catch(Exception $e){
            return response([
                'error' => 'Unable to add payment method - please try a different card',
            ], 400);
        }

        return response($paymentMethod, 201);
    }

    /**
     * Show a record
     * 
     * @param Request $request
     * @param \App\Models\PaymentMethod $paymentMethod
     * 
     * @return Response
     */
    public function read(Request $request, PaymentMethod $paymentMethod)
    {
        return response($paymentMethod);
    }

    /**
     * Update a record(Default)
     * 
     * @param Request $request
     * @param \App\Models\PaymentMethod $paymentMethod
     * 
     * @return Response
     */
    public function makeDefault(Request $request, PaymentMethod $paymentMethod)
    {
        $user = $request->user();
    
        PaymentMethod::where('account_id', $user->account_id)   
                      ->update([ 
                          'primary_method' => false 
                        ]);

        $paymentMethod->primary_method = true;
        $paymentMethod->save();

        return response($paymentMethod);
    }

    /**
     * Delete a payment method
     * 
     * @param Request $request
     * @param \App\Models\PaymentMethod $paymentMethod
     * 
     * @return Response
     */
    public function delete(Request $request, PaymentMethod $paymentMethod)
    {
        $user = $request->user();
    
        //  Do not allow deleting this card it's your primary payment method
        if( $paymentMethod->primary_method ){
            return response([
                'error' => 'You cannot delete your primary payment method'
            ], 400);
        }

        $paymentMethod->delete();

        return response([
            'message' => 'Deleted.'        
        ], 200);
    }
}
