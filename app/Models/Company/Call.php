<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'session_id',

        'external_id',
        'direction',
        'status',
        'duration',

        'caller_first_name',
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
        'caller_id_enabled',
        'forwarded_to',

        'duration',

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

    protected $dateFormat = 'Y-m-d H:i:s.u';  

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
