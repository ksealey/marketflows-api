<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \App\Models\PaymentMethod;
use \App\Jobs\PayUnpaidStatementsJob;
use Validator;
use Exception;
use DB;

class PaymentMethodController extends Controller
{
    
    public $fields = [
        'payment_methods.last_4',
        'payment_methods.brand',
        'payment_methods.created_at',
        'payment_methods.created_at'
    ];

    /**
     * List Payment Methods
     * 
     */
    public function list(Request $request)
    {
        $query = PaymentMethod::where('account_id', $request->user()->account_id);

        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            [],
            $this->fields,
            'payment_methods.created_at'
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
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $paymentMethod = PaymentMethod::createFromToken(
            $request->token, 
            $request->user(), 
            true 
        );

        //  
        //  If there are unpaid statements, add job
        //
        PayUnpaidStatementsJob::dispatch($paymentMethod);

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
        $paymentMethod->errors = $paymentMethod->errors;
        
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
    public function makePrimary(Request $request, PaymentMethod $paymentMethod)
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

        $paymentMethod->deleted_at = now();
        $paymentMethod->deleted_by = $user->id;
        $paymentMethod->save();

        return response([
            'message' => 'Deleted'        
        ], 200);
    }

    /**
     * Export results
     * 
     */
    public function export(Request $request)
    {
        return parent::exportResults(
            PaymentMethod::class,
            $request,
            [],
            $this->fields,
            'payment_methods.created_at'
        );
    }
}
