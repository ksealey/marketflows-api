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

    protected $appends = [
        'kind',
        'link'
    ];

    public function getKindAttribute()
    {
        return 'Payment';
    }

    public function getLinkAttribute()
    {
        return null;
    }
}
