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

    protected $appends = [
        'kind',
        'link'
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
        return array_sum(array_column($this->items->toArray(), 'total'));
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
