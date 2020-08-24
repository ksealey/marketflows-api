<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingStatement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'billing_id',
        'billing_period_starts_at',
        'billing_period_ends_at'
    ];

    public function items()
    {
        return $this->hasMany('App\Models\BillingStatementItem');
    }

    public function billing()
    {
        return $this->belongsTo('App\Models\Billing');
    }

    public function payment()
    {
        return $this->belongsTo('App\Models\Payment');
    }

    public function total()
    {
        return array_sum(array_column($this->items->toArray(), 'total'));
    }
}
