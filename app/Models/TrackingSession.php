<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSession extends Model
{
    protected $fillable = [
        'id',
        'uuid',
        'tracking_entity_id',
        'company_id',
        'phone_number_pool_id',
        'phone_number_id',
        'ip',
        'host',
        'device_width',
        'device_height',
        'device_type',
        'device_brand',
        'device_os',
        'browser_type',
        'browser_version',
        'source',
        'medium',
        'content',
        'campaign',
        'token',
        'created_at',
        'updated_at',
        'last_heartbeat_at',
        'ended_at'
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u'; 
    
    protected $hidden  = [
        'tracking_entity_id',
        'token',
        'last_heartbeat_at'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'ended_at',
        'last_heartbeat_at'
    ];
}
