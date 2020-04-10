<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class BankedPhoneNumber extends Model
{
    protected $fillable = [
        'external_id',
        'country_code',
        'number',
        'voice',
        'sms',
        'mms',
        'toll_free',
        'calls',
        'purchased_at'
    ];
}
