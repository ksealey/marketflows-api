<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\BillingStatement;
use DateTime;
use DB;

class Billing extends Model
{
    use SoftDeletes;

    const MAX_ATTEMPTS = 3;
    
    protected $table = 'billing';

    protected $fillable = [
        'account_id',
        'stripe_id',
        'period_starts_at',
        'period_ends_at',
        'bill_at',
        'last_billed_at',
        'attempts',
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
        $pastDue = DB::table('billing_statement_items')->select(
                        DB::raw('
                            CASE 
                                WHEN SUM(total) IS NULL 
                                    THEN 0.00
                                ELSE 
                                    SUM(total)
                            END AS past_due')
                    )
                    ->whereIn('billing_statement_id', function($query){
                        $query->select('id')
                            ->from('billing_statements')
                            ->where('account_id', $this->id)
                            ->whereNull('paid_at');
                    })
                    ->first()
                    ->past_due;

        return $pastDue;
    }


    public function getCurrentBillingPeriodAttribute()
    {
        return [
            'start' => new DateTime($this->period_starts_at . ' 00:00:00'),
            'end'   => new DateTime($this->period_ends_at . ' 23:59:59'),
        ];
    }

    public function getUnpaidStatementsAttribute()
    {
        return BillingStatement::where('account_id', $this->account_id)
                               ->whereNull('paid_at')
                               ->orderBy('billing_period_starts_at', 'ASC')
                               ->get();
    }
}
