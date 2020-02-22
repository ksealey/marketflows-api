<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;

class Session extends EventModel
{
    protected $fillable = [
        'id',
        'persisted_id',
        'company_id',
        'phone_number_id',
        'first_session',
        'ip',
        'host',
        'device_width',
        'device_height',
        'device_type',
        'device_brand',
        'device_os',
        'browser_type',
        'browser_version',
        'token',
        'created_at',
        'updated_at',
        'ended_at'
    ];
}
