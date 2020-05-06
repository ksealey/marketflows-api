<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use \App\Traits\PerformsExport;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;

class BillingStatement extends Model
{
    use SoftDeletes, PerformsExport;

    protected $fillable = [
        'account_id',
        'billing_period_starts_at',
        'billing_period_ends_at',
        'payment_method_id',
        'paid_at',
        'charge_id'
    ];

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'billing_period'    => 'Billing Period',
            'total'             => 'Total',
            'paid_at_local'     => 'Payment Date',
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Billing Statements';
    }

    static public function exportQuery($user, array $input)
    {
        return BillingStatement::select([
            '*',
            DB::raw("CONCAT(DATE_FORMAT(CONVERT_TZ(billing_period_starts_at,'UTC','" . $user->timezone . "'),  '%b %d, %Y'), ' - ', DATE_FORMAT(CONVERT_TZ(billing_period_ends_at,'UTC','" . $user->timezone . "'),'%b %d, %Y')) as billing_period"),
            DB::raw("DATE_FORMAT(CONVERT_TZ(paid_at,'UTC','" . $user->timezone . "'), '%b %d, %Y') as paid_at_local"),
            DB::raw('(SELECT ROUND(SUM(total), 2) from billing_statement_items where billing_statement_id = billing_statements.id) as total'),
            DB::raw('(SELECT CONVERT(FORMAT(ROUND(SUM(total), 2), 2), CHAR) from billing_statement_items where billing_statement_id = billing_statements.id) as total_formatted')
        ])->where('account_id', $user->account_id);
    }

    public function statement_items()
    {
        return $this->hasMany('App\Models\BillingStatementItem');
    }

    public function getTotalAttribute($total = null)
    {
        if( $total === null ){
            $total = 0;
            $items = $this->statement_items;
            foreach( $items as $item ){
                $total += $item->total;
            }
        }
        
        return round(floatval($total), 2);
    }
}
