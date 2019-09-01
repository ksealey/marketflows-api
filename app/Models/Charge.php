<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charge extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'payment_method_id',
        'external_id',
        'amount',
        'description',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    public function paymentMethod()
    {
        return $this->belongsTo('\App\Models\PaymentMethod');
    }
}
