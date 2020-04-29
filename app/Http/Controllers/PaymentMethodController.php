<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \App\Models\PaymentMethod;
use \App\Events\PaymentMethodEvent;
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
        $fields = [
            'payment_methods.last_4',
            'payment_methods.brand',
            'payment_methods.created_at',
            'payment_methods.created_at'
        ];

        $query = PaymentMethod::where('account_id', $request->user()->account_id);

        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            [],
            $fields
        );
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

        $user = $request->user();

        $paymentMethod = PaymentMethod::createFromToken(
            $request->token, 
            $user, 
            $request->primary_method
        );

        event( new PaymentMethodEvent($user, [$paymentMethod], 'create') );

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
