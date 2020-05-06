<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BillingStatement;
use DB;

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
        ])->where('account_id', $user->account_id);
        
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
}