<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DateTime;
use DB;

class Billing extends Model
{
    use SoftDeletes;
    
    protected $table = 'billing';

    protected $fillable = [
        'account_id',
        'stripe_id',
        'bill_at',
        'last_billed_at',
        'billing_failed_at',
        'failed_billing_attempts',
        'last_error',
        'locked_at'
    ];

    public $appends = [
        'current_billing_period'
    ];

    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    public function getPastDueAmountAttribute()
    {
        $pastDue = DB::table('statement_items')->select(
                        DB::raw('
                            CASE 
                                WHEN SUM(total) IS NULL 
                                    THEN 0.00
                                ELSE 
                                    SUM(total)
                            END AS past_due')
                    )
                    ->whereIn('statement_id', function($query){
                        $query->select('id')
                            ->from('statements')
                            ->where('account_id', $this->id)
                            ->whereNull('paid_at');
                    })
                    ->first()
                    ->past_due;

        return $pastDue;
    }

    public function getCurrentBillingPeriodAttribute()
    {
        $start = new DateTime($this->last_billed_at ?: $this->created_at);
        $end   = new DateTime($this->bill_at);

        return [
            'start' => $start,
            'end'   => $end,
        ];
    }
}
