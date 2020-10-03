<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class KeywordTrackingPoolSession extends Model
{
    public $fillable = [
        'guuid',
        'uuid',
        'keyword_tracking_pool_id',
        'phone_number_id',
        'device_width',
        'device_height',
        'device_type',
        'device_browser', 
        'device_platform',
        'http_referrer',
        'landing_url',
        'last_url',
        'token',
        'ended_at'
    ];

    public function phone_number()
    {
        return $this->belongsTo(\App\Models\Company\PhoneNumber::class);
    }

    public function keyword_tracking_pool()
    {
        return $this->belongsTo(\App\Models\Company\KeywordTrackingPool::class);
    }
}
