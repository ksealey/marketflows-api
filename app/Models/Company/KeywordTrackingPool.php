<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class KeywordTrackingPool extends Model
{
    public $fillable = [
        'uuid',
        'account_id',
        'company_id',
        'phone_number_config_id',
        'name',
        'swap_rules',
        'disabled_at',
        'created_at',
        'updated_at',
        'deleted_at',
        'created_by'
    ];

    public $appends = [
        'link',
        'kind',
    ];

    public $hidden = [
        'deleted_at'
    ];

    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function phone_number_config()
    {
        return $this->belongsTo('\App\Models\Company\PhoneNumberConfig');
    }

    public function phone_numbers()
    {
        return $this->hasMany(\App\Models\Company\PhoneNumber::class);
    }

    public function getSwapRulesAttribute($rules)
    {
        return json_decode($rules);
    }

    public function getKindAttribute()
    {
        return 'KeywordTrackingPool';
    }

    public function getLinkAttribute()
    {
        return route('read-keyword-tracking-pool', [
            'company'             => $this->company_id,
            'keywordTrackingPool' => $this->id       
        ]);
    }

    public static function accessibleFields()
    {
        return [
            'keyword_tracking_pools.id',
            'keyword_tracking_pools.name',
            'keyword_tracking_pools.phone_number_config_id',
            'keyword_tracking_pools.created_at'
        ];
    }
}
