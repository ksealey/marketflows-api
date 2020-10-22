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
        'billing_period_ends_at',
        'payment_attempts',
        'next_payment_attempt_at',
        'locked_at',
        'failed_intent_id'
    ];

    protected $appends = [
        'kind',
        'link'
    ];

    protected $hidden = [
        'locked_at',
        'failed_intent_id'
    ];

    public function getKindAttribute()
    {
        return 'BillingStatement';
    }

    public function getLinkAttribute()
    {
        return route('read-statement', [
            'billingStatement' => $this->id
        ]);
    }

    public function getTotalAttribute()
    {
        $totals   = array_column($this->items->toArray(), 'total');
        $totalSum = array_sum($totals);

        return round($totalSum, 2);
    }

    public function getTotalFormattedAttribute()
    {
        return number_format($this->total, 2);
    }

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
}
