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
        'contact_id',
        
        'type',
        'category',
        'sub_category',

        'external_id',
        'direction',
        'status',
        'duration',

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

    public function contact()
    {
        return $this->belongsTo('\App\Models\Company\Contact');
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
