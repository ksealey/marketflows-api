<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargeError extends Model
{
    use SoftDeletes;

    const PAYMENT_DECLINED = 'Your card was declined.';
    
    protected $fillable = [
        'payment_method_id',
        'amount',
        'description',
        'error',
        'exception',
        'resolved'
    ];

    protected $hidden = [
        'exception',
        'deleted_at'
    ];

    public function paymentMethod()
    {
        return $this->belongsTo('\App\Models\PaymentMethod');
    }
}