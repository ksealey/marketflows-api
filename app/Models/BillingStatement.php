<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingStatement extends Model
{
    protected $fillable = [
        'account_id',
        'billing_period_starts_at',
        'billing_period_ends_at',
        'payment_method_id',
        'paid_at',
        'charge_id'
    ];

    public function statement_items()
    {
        return $this->hasMany('App\Models\BillingStatementItem');
    }

    public function getTotalAttribute()
    {
        $total = 0;
        $items = $this->statement_items;
        foreach( $items as $item ){
            $total += $item->total;
        }
        return $total;
    }
}
