<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class CreditCode extends Model
{
    use SoftDeletes;

    static public function generate($amount, $accountId = null)
    {
        return self::create([
            'account_id' => $accountId,
            'code'       => 'MKT-' . str_random(16),
            'amount'     => floatval($amount)
        ]);
    }
}
