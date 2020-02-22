<?php

namespace App\Models\Company\PhoneNumber;

use Illuminate\Database\Eloquent\Model;

class Call extends Model
{
    protected $fillable = [
        'account_id',
        'company_id',
        'phone_number_id',
        'toll_free',
        'category',
        'sub_category',
        
        'phone_number_pool_id',
        'session_id',

        'caller_id_enabled',
        'recording_enabled',
        
        'forwarded_to',

        'external_id',
        'direction',
        'status',

        'caller_first_name',
        'caller_last_name',
        'caller_country_code',
        'caller_number',
        'caller_city',
        'caller_state',
        'caller_zip',
        'caller_country',
        
        'dialed_country_code',
        'dialed_number',
        'dialed_city',
        'dialed_state',
        'dialed_zip',
        'dialed_country',
        
        'source',
        'medium',
        'content',
        'campaign',

        'recording_enabled',
        'caller_id_enabled',
        'forwarded_to',

        'duration',

        'cost'
    ];

    protected $hidden = [
        'external_id'
    ];

    public function phoneNumber()
    {
        return $this->belongsTo('\App\Models\Company\PhoneNumber');
    }
}
