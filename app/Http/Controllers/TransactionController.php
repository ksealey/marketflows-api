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
            'order_by'          => 'in:company_id,description,created_at,amount',
            'company_id'        => 'numeric',
            'payment_method_id' => 'numeric'
        ];

        $account = $request->user()->account;

        //  Build Query
        $query = Transaction::where('account_id', $account->id);

        if( $request->company_id )
            $query->where('company_id', $request->company_id);
        
        if( $request->search )
            $query->where('description', 'like', '%' . $request->search . '%');

        //  Pass along to parent for listing
        return $this->listRecords(
            $request,
            $query,
            $rules, 
            $account->timezone
        );
    }
}
