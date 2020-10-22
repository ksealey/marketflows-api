<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\Account;
use App\Models\Billing;
use App\Models\BillingStatement;
use App\Helpers\PaymentManager;
use App\Mail\BillingReceipt;
use DB;
use App;
use Mail;
use \Carbon\Carbon;

class BillingStatementController extends Controller
{
    public $fields = [
       'billing_statements.billing_period_starts_at',
       'billing_statements.billing_period_ends_at',
       'billing_statements.payment_method_id',
       'billing_statements.paid_at',
    ];

    public function list(Request $request)
    {
        $user  = $request->user();
        $query = BillingStatement::select([
            '*',
            DB::raw('(SELECT ROUND(SUM(total), 2) from billing_statement_items where billing_statement_id = billing_statements.id) as total'),
            DB::raw('(SELECT CONVERT(FORMAT(ROUND(SUM(total), 2), 2), CHAR) from billing_statement_items where billing_statement_id = billing_statements.id) as total_formatted')
        ])->where('billing_id', function($query) use($user){
            $query->select('id')
                  ->from('billing')
                  ->where('account_id', $user->account_id);
        });
        
        return parent::results(
            $request,
            $query,
            [],
            $this->fields,
            'billing_statements.created_at'
        );
    }

    
    public function read(Request $request, BillingStatement $billingStatement)
    {
        $billingStatement->total           = $billingStatement->total;
        $billingStatement->total_formatted = $billingStatement->total_formatted;

        if( $billingStatement->payment_id ){
            $payment = $billingStatement->payment;
            $payment->payment_method = $payment->payment_method;
            $billingStatement->payment = $payment;
        }
        
        return response($billingStatement);
    }

    public function export(Request $request)
    {
        return parent::exportResults(
            BillingStatement::class,
            $request,
            [],
            $this->fields,
            'billing_statements.created_at'
        );
    }

    public function pay(Request $request, BillingStatement $billingStatement)
    {
        if( $billingStatement->paid_at ){
            return response([
                'error' => 'Billing statement paid'
            ], 400);
        }

        if( $billingStatement->locked_at ){ // Currently being processed
            return response([
                'error' => 'Cannot complete payment at this time - Please try again later'
            ], 400);
        }

        $validator = validator($request->input(), [
            'payment_method_id' => 'bail|required|exists:payment_methods,id'
        ]);
        
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $paymentMethod = PaymentMethod::find($request->payment_method_id);
        $user          = $request->user();
        if( $paymentMethod->account_id !== $user->account_id )
            return response([
                'error' => 'Payment method does not exist'
            ], 400);

        $paymentManager = App::make(PaymentManager::class);
        $results        = $paymentManager->charge($paymentMethod, $billingStatement);
        $payment        = $results->payment;
        
        if( ! $payment ){
            return response([
                'error' => 'Unable to process payment - please try another payment method'
            ], 400);
        }

        //  Pay bill
        $billingStatement->payment_id = $payment->id;
        $billingStatement->paid_at    = now();
        $billingStatement->save();

        //  Unsuspend account if suspended
        $account = $paymentMethod->account;
        if( $account->suspension_code == Account::SUSPENSION_CODE_OUSTANDING_BALANCE )
            $account->unsuspend();

        //  Mail reciept
        Mail::to($user)
            ->queue(new BillingReceipt($user, $billingStatement, $paymentMethod, $payment));

        $payment->payment_method = $paymentMethod;
        
        return response($payment, 201);
    }
}