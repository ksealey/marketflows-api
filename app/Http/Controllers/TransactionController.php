<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;

class TransactionController extends Controller
{
    public function list(Request $request)
    {
        //  Set additional rules
        $rules = [
            'order_by'          => 'in:company_id,label,amount,created_at,',
            'company_id'        => 'numeric',
            'payment_method_id' => 'numeric'
        ];

        $user    = $request->user();

        //  Build Query
        $query = Transaction::where('account_id', $user->account_id);

        if( $request->company_id )
            $query->where('company_id', $request->company_id);
        
        if( $request->search )
            $query->where('label', 'like', '%' . $request->search . '%');

        //  Pass along to parent for listing
        return $this->listRecords(
            $request,
            $query,
            $rules
        );
    }

    public function read(Request $request, Transaction $transaction)
    {
        return response($transaction);
    }
}
