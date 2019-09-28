<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebProfileIdentity extends Model
{
    protected $fillable = [
        'uuid',
        'web_profile_id',
        'external_id',
        'domain',
        'first_name',
        'last_name',
        'email',
        'home_phone',
        'mobile_phone',
        'company',
        'address_street',
        'address_street_2',
        'address_city',
        'address_state',
        'address_postcode',
        'address_country',
        'location_lat',
        'location_lng'
    ];
}
