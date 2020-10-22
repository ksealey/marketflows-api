<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class Payment extends Model
{
    protected $fillable = [
        'payment_method_id',
        'billing_statement_id',
        'total',
        'external_id'
    ];

    protected $appends = [
        'kind',
        'link'
    ];

    static public function exports() : array
    {
        return [
            'id'                    => 'Id',
            'billing_statement_id'  => 'Billing Statement Id',
            'total'                 => 'Total',
            'created_at_local'      => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Payments';
    }

    static public function exportQuery($user, array $input)
    {
        return Payment::select([
            'payments.*',
            DB::raw('CONVERT(FORMAT(ROUND(total, 2), 2), CHAR) AS total_formatted'),
            DB::raw("DATE_FORMAT(CONVERT_TZ(payments.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local") 
        ])
        ->where('payment_method_id', $input['payment_method_id']);
    }

    public function getKindAttribute()
    {
        return 'Payment';
    }

    public function getLinkAttribute()
    {
        return null;
    }

    public function payment_method()
    {
        return $this->belongsTo('\App\Models\PaymentMethod');
    }
}
