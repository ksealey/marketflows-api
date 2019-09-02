<?php

namespace App\Models\Company\PhoneNumber;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = [
        'phone_number_id',
        'external_id',
        'direction',
        'status',
        'duration',
        'from_country_code',
        'from_number',
        'from_city',
        'from_state',
        'from_zip',
        'from_country',
        'to_country_code',
        'to_number',
        'to_city',
        'to_state',
        'to_zip',
        'to_country'
    ];

    public function phoneNumber()
    {
        return $this->belongsTo('\App\Models\Company\PhoneNumber');
    }
}
