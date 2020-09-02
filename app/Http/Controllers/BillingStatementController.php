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
        $billingStatement->payment_method  = $billingStatement->payment_method;
        if( $billingStatement->paid_at && ! $billingStatement->payment_method ){
            $billingStatement->payment_method_last_4  = PaymentMethod::withTrashed()->find($billingStatement->payment_method_id)->last_4;
        }
        $billingStatement->statement_items = $billingStatement->statement_items;

        $total = $billingStatement->total;
        $billingStatement->total           = $total;
        $billingStatement->total_formatted = number_format($total, 2);

        return response($billingStatement);
    }

    public function current(Request $request)
    {
        $account = $request->user()->account;
        $billing = $account->billing;
        $total   = 0;

        $billingPeriodStart     = new Carbon($billing->billing_period_starts_at);
        $billingPeriodEnd       = new Carbon($billing->billing_period_ends_at);

        $serviceQuantity        = $billing->quantity(Billing::ITEM_SERVICE, $billingPeriodStart, $billingPeriodEnd);
        $serviceTotal           = $billing->total(Billing::ITEM_SERVICE, $serviceQuantity);

        $localNumberQuantity    = $billing->quantity(Billing::ITEM_NUMBERS_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localNumberTotal       = $billing->total(Billing::ITEM_NUMBERS_LOCAL, $localNumberQuantity);

        $tollFreeNumberQuantity = $billing->quantity(Billing::ITEM_NUMBERS_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeNumberTotal    = $billing->total(Billing::ITEM_NUMBERS_TOLL_FREE, $tollFreeNumberQuantity);

        $localMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_LOCAL, $billingPeriodStart, $billingPeriodEnd);
        $localMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_LOCAL, $localMinutesQuantity);

        $tollFreeMinutesQuantity= $billing->quantity(Billing::ITEM_MINUTES_TOLL_FREE, $billingPeriodStart, $billingPeriodEnd);
        $tollFreeMinutesTotal   = $billing->total(Billing::ITEM_MINUTES_TOLL_FREE, $tollFreeMinutesQuantity);

        $transMinutesQuantity   = $billing->quantity(Billing::ITEM_MINUTES_TRANSCRIPTION, $billingPeriodStart, $billingPeriodEnd);
        $transMinutesTotal      = $billing->total(Billing::ITEM_MINUTES_TRANSCRIPTION, $transMinutesQuantity);

        $storageQuantity        = $billing->quantity(Billing::ITEM_STORAGE_GB, $billingPeriodStart, $billingPeriodEnd);
        $storageTotal           = $billing->total(Billing::ITEM_STORAGE_GB, $storageQuantity);

        $total = $serviceTotal + $localNumberTotal + $tollFreeNumberTotal + $localMinutesTotal + $tollFreeMinutesTotal + $transMinutesTotal + $storageTotal;
        $items = [
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_SERVICE),
                'quantity'             => $serviceQuantity,
                'price'                => $billing->price(Billing::ITEM_SERVICE),
                'total'                => $serviceTotal
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_NUMBERS_LOCAL),
                'quantity'             => $localNumberQuantity,
                'price'                => $billing->price(Billing::ITEM_NUMBERS_LOCAL),
                'total'                => $localNumberTotal
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_NUMBERS_TOLL_FREE),
                'quantity'             => $tollFreeNumberQuantity,
                'price'                => $billing->price(Billing::ITEM_NUMBERS_TOLL_FREE),
                'total'                => $tollFreeNumberTotal,
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_MINUTES_LOCAL),
                'quantity'             => $localMinutesQuantity,
                'price'                => $billing->price(Billing::ITEM_MINUTES_LOCAL),
                'total'                => $localMinutesTotal,
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_MINUTES_TOLL_FREE),
                'quantity'             => $tollFreeMinutesQuantity,
                'price'                => $billing->price(Billing::ITEM_MINUTES_TOLL_FREE),
                'total'                => $tollFreeMinutesTotal,
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_MINUTES_TRANSCRIPTION),
                'quantity'             => $transMinutesQuantity,
                'price'                => $billing->price(Billing::ITEM_MINUTES_TRANSCRIPTION),
                'total'                => $transMinutesTotal
            ],
            [
                'type'                 => 'STANDARD',
                'label'                => $billing->label(Billing::ITEM_STORAGE_GB),
                'quantity'             => $storageQuantity,
                'price'                => $billing->price(Billing::ITEM_STORAGE_GB),
                'total'                => $storageTotal
            ],
        ];

        foreach( $billing->account->services as $service ){
            $_serviceTotal = $service->total();

            $items[] = [
                'type'                 => 'SERVICE',
                'label'                => $service->label(),
                'quantity'             => $service->quantity(),
                'price'                => $service->price(),
                'total'                => $_serviceTotal
            ];

            $total += $_serviceTotal;
        }

        return response([
            'items' => $items,
            'total' => $total
        ]);
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
        $payment        = $paymentManager->charge($paymentMethod, $billingStatement->total());
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

        return response([
            'message' => 'Paid'
        ]);
    }
}