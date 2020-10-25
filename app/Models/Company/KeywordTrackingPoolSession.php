<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class KeywordTrackingPoolSession extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public $fillable = [
        'guuid',
        'uuid',
        'keyword_tracking_pool_id',
        'phone_number_id',
        'contact_id',
        'device_width',
        'device_height',
        'device_type',
        'device_browser', 
        'device_platform',
        'http_referrer',
        'landing_url',
        'last_url',
        'source', 
        'medium',
        'content',
        'campaign',
        'keyword',
        'is_organic',
        'is_paid',
        'is_direct',
        'is_referral',
        'is_search',
        'token',
        'active',
        'last_activity_at',
        'end_after',
        'ended_at',
        'created_at',
        'updated_at',
    ];

    protected $appends = [
        'kind'
    ];

    public function getKindAttribute()
    {
        return 'KeywordTrackingPoolSession';
    }

    public function phone_number()
    {
        return $this->belongsTo(\App\Models\Company\PhoneNumber::class);
    }

    public function keyword_tracking_pool()
    {
        return $this->belongsTo(\App\Models\Company\KeywordTrackingPool::class);
    }
}
