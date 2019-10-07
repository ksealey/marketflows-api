<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebSession extends Model
{
    protected $hidden = [
        'id',
        'web_profile_identity_id',
        'web_device_id',
        'campaign_id',
        'phone_number_id',


    ];
    protected $fillable = [
        'uuid',
        'web_profile_identity_id',
        'web_device_id',
        'campaign_id',
        'campaign_domain_id',
        'phone_number_pool_id',
        'phone_number_id',
        'ip'
    ];
}
