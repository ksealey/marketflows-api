<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;

class PaymentSetup extends Model
{
    protected $fillable = [
        'customer_id',
        'intent_id',
        'intent_client_secret',
        'email',
        'expires_at',
    ];
    
    protected $appends = [
        'kind'
    ];

    protected $hidden = [
        'intent_client_secret'
    ];

    public function getKindAttribute()
    {
        return 'PaymentSetup';
    }
}
