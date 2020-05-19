<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\TrackingEntity;

class Call extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'account_id',
        'company_id',
        'phone_number_id',
        'type',
        'category',
        'sub_category',
        
        'phone_number_pool_id',
        'tracking_entity_id',

        'external_id',
        'direction',
        'status',
        'duration',

        'caller_name',
        'caller_last_name',
        'caller_country_code',
        'caller_number',
        'caller_city',
        'caller_state',
        'caller_zip',
        'caller_country',
        
        'source',
        'medium',
        'content',
        'campaign',

        'recording_enabled',
        'forwarded_to',

        'duration',
        'first_call',

        'cost',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'external_id'
    ];

    protected $appends = [
        'link',
        'kind',
    ];

    protected $casts = [
        'first_call' => 'boolean'
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';  

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function tracking_entity()
    {
        return TrackingEntity::find($this->tracking_entity_id);
        return $this->hasOne('\App\Models\TrackingEntity');
    }
    
    public function getLinkAttribute()
    {
        return route('read-call', [
            'companyId'  => $this->company_id,
            'callId'    => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'Call';
    }
}
