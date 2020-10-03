<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use \App\Models\Company\PhoneNumber;
use DB;

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
        return $this->hasMany(PhoneNumber::class);
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

    public function assignNumber()
    {
        $phoneNumber = PhoneNumber::select([
                                        'phone_numbers.*',
                                        DB::raw('(
                                            SELECT COUNT(*) 
                                                FROM keyword_tracking_pool_sessions 
                                            WHERE phone_numbers.id = keyword_tracking_pool_sessions.phone_number_id
                                                AND keyword_tracking_pool_sessions.ended_at IS NOT NULL
                                        ) AS active_assignments')
                                    ])
                                    ->where('keyword_tracking_pool_id', $this->id)
                                    ->orderBy('active_assignments', 'ASC')
                                    ->orderBy('last_assigned_at', 'ASC')
                                    ->orderBy('id', 'ASC')
                                    ->first();

        if( ! $phoneNumber ) return null;

        $phoneNumber->last_assigned_at = now()->format('Y-m-d H:i:s.u');
        $phoneNumber->total_assignments++;
        $phoneNumber->save();

        return $phoneNumber;
    }
}
