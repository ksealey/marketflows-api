<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use \App\Models\PaymentMethod;
use \App\Models\Payment;
use \App\Helpers\PaymentManager;
use App\Models\BillingStatement;
use \Carbon\Carbon;
use Validator;
use Exception;
use DB;
use App;

class PaymentMethodController extends Controller
{
    
    public $fields = [
        'payment_methods.last_4',
        'payment_methods.brand',
        'payment_methods.expiration',
        'payment_methods.created_at',
        'payment_methods.last_used_at'
    ];

    public $paymentFields = [
        'payments.id',
        'billing_statements.id',
        'payments.total',
        'payments.created_at'
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

    public function createIntent(Request $request)
    {
        $paymentManager = App::make(PaymentManager::class);
        $billing        = $request->user()->account->billing;
        $intent         = $paymentManager->createIntent($billing->external_id);

        return response($intent);
    }

    /**
     * Create a record
     * 
     * @param Request $request
     * @return Response
     */
    public function create(Request $request)
    {
        $rules  = [
            'payment_method_id' => 'required',// stripe payment method id
            'primary_method'    => 'boolean'
        ];

        $validator = Validator::make($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user           = $request->user();
        $paymentManager = App::make(PaymentManager::class);
        $paymentMethods = $paymentManager->getPaymentMethods($user->account->billing->external_id);

        $paymentMethod = null;
        foreach( $paymentMethods as $pm ){
            if( $request->payment_method_id == $pm->id )
                $paymentMethod = $pm;
        }
        $card = $paymentMethod->card;
        
        //  Add initial payment method
        $expiration = new Carbon($card->exp_year . '-' . $card->exp_month . '-01 00:00:00'); 
        $expiration->endOfMonth();
        
        if( $request->primary_method ){
            PaymentMethod::where('account_id', $user->account->id)
                         ->update(['primary_method' => false]);
        }

        $paymentMethod = PaymentMethod::create([
            'account_id'     => $user->account_id,
            'created_by'     => $user->id,
            'external_id'    => $paymentMethod->id,
            'last_4'         => $card->last4,
            'type'           => $card->funding,
            'brand'          => ucfirst($card->brand),
            'expiration'     => $expiration->format('Y-m-d'),
            'primary_method' => !!$request->primary_method
        ]);

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
        $paymentMethod->payments = $paymentMethod->payments;
        
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
     * Handle authentication for a payment method
     * 
     * @param Request $request
     * @param \App\Models\PaymentMethod $paymentMethod
     * 
     * @return Response
     */
    public function authenticate(Request $request, PaymentMethod $paymentMethod)
    {
        if( ! $paymentMethod->last_error_intent_id ){
            return response([
                'error' => 'No authentication required'
            ], 400);
        }

        $paymentManager = App::make(PaymentManager::class);
        $intent         = $paymentManager->getIntent($paymentMethod->last_error_intent_id);
        if( $intent ){
            if( $intent->status === 'succeeded' ){
                $paymentMethod->last_error = null;
                $paymentMethod->last_error_code = null;
                $paymentMethod->last_error_intent_id = null;
                $paymentMethod->last_error_intent_secret = null;
                $paymentMethod->save();

                $failedStatement = BillingStatement::where('intent_id', $intent->id)->first();
                if( $failedStatement ){
                    Payment::create([
                        'payment_method_id'     => $paymentMethod->id,
                        'billing_statement_id'  => $failedStatement->id,
                        'external_id'           => $intent->id,
                        'total'                 => $failedStatement->total
                    ]);
                    $failedStatement->paid_at = now();
                    $failedStatement->save();
                }

            }
        }

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

    public function listPayments(Request $request, PaymentMethod $paymentMethod)
    {
        $query  = Payment::select([
                            'payments.*',
                            DB::raw('CONVERT(FORMAT(ROUND(total, 2), 2), CHAR) AS total_formatted')
                        ])
                        ->where('payment_method_id', $paymentMethod->id);

        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            [],
            $this->paymentFields,
            'payments.created_at'
        );
    }

    public function exportPayments(Request $request, PaymentMethod $paymentMethod)
    {
        $request->merge([
            'payment_method_id' => $paymentMethod->id
        ]);
        
        return parent::exportResults(
            Payment::class,
            $request,
            [],
            $this->paymentFields,
            'payments.created_at'
        );
    }
}
