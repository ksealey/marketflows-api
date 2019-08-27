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
                'error' => $validator->error()->first()
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

        return response([
            'message'        => 'created',
            'payment_method' => $paymentMethod
        ], 201);
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
        return response([
            'message'        => 'success',
            'payment_method' => $paymentMethod
        ], 200);
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

        return response([
            'message'        => 'updated',
            'payment_method' => $paymentMethod
        ], 200);
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
            'message' => 'deleted'        
        ], 200);
    }

    /**
     * Payment Methods
     * 
     */
    public function list(Request $request)
    {
        $user  = $request->user();

        $start = intval($request->input('start', 0));
        $limit = intval($request->input('limit', 0)) ?: 25;

        $query      = PaymentMethod::where('account_id', $user->account_id); 
        $totalCount = $query->count();

        $paymentMethods = $query->limit($limit)
                                ->offset($start)
                                ->get();
        
        return response([
            'message'         => 'success',
            'ok'              => true,
            'payment_methods' => $paymentMethods,
            'result_count'    => count($paymentMethods),
            'total_count'     => $totalCount
        ]);
    }


}
