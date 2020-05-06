<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethodError extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'payment_method_id',
        'error'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    public function payment_method()
    {
        return $this->belongsTo('\App\Models\PaymentMethod');
    }
}
