<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charge extends Model
{  
    public $timestamps = false;
     
    protected $fillable = [
        'payment_method_id',
        'external_id',
        'amount',
        'description',
        'created_at'
    ];
    
    protected $appends = [
        'link',
        'kind'
    ];

     /**
     * Appends
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-charge', [
            $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'Charge';
    }

    public function paymentMethod()
    {
        return $this->belongsTo('\App\Models\PaymentMethod');
    }
}
