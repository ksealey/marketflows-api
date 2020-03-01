<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use DB;

class TransactionController extends Controller
{
    public function list(Request $request)
    {
        //  Set additional rules
        $rules = [
            'order_by'          => 'in:transactions.label,transactions.amount,transactions.created_at,companies.name',
            'company_id'        => 'numeric',
            'payment_method_id' => 'numeric'
        ];

        $searchFields = [
            'transactions.label', 
            'transactions.amount',
            'companies.name'
        ];

        //  Build Query
        $query = DB::table('transactions')
                    ->select(['transactions.*', 'companies.name AS company_name', 'companies.deleted_at AS company_deleted_at'])
                    ->leftJoin('companies', 'companies.id', 'transactions.company_id')
                    ->where('transactions.account_id', $request->user()->account_id);

        if( $request->company_id ) // No need to check since it will always be filtered by account id
            $query->where('company_id', $request->company_id);

        //  Pass along to parent for listing
        return parent::results(
            $request,
            $query,
            $rules,
            $searchFields,
            'transactions.created_at'
        );
    }

    public function read(Request $request, Transaction $transaction)
    {
        return response($transaction);
    }
}
