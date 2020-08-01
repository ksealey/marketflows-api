<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'payment_method_id',
        'total',
        'external_id'
    ];

    public function getKindAttribute()
    {
        return 'Payment';
    }
}
