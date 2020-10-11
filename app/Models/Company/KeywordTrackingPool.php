<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\KeywordTrackingPoolSession;
use \App\Models\Company\Call;
use DB;


class KeywordTrackingPool extends Model
{ 
    use SoftDeletes;

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

    public function getPhoneNumbersAttribute()
    {
        $query = PhoneNumber::select([
            'phone_numbers.*',
            DB::raw('(SELECT COUNT(*) FROM keyword_tracking_pool_sessions WHERE keyword_tracking_pool_sessions.phone_number_id = phone_numbers.id AND ended_at IS NULL) AS active_assignments')
        ])->where('keyword_tracking_pool_id', $this->id);
        
        return $query->get();
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

    public function getCallCountAttribute()
    {
        return Call::where('keyword_tracking_pool_id', $this->id)->count();
    }

    public function getTotalAssignmentsAttribute()
    {
        return DB::table('phone_numbers')
                 ->select([
                     DB::raw('SUM(phone_numbers.total_assignments) AS _total_assignments')
                 ])
                 ->where('phone_numbers.keyword_tracking_pool_id', $this->id)
                 ->first()
                 ->_total_assignments;
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
        $query = PhoneNumber::select([
                                    'phone_numbers.*',
                                    DB::raw('(
                                        SELECT COUNT(*) 
                                            FROM keyword_tracking_pool_sessions 
                                        WHERE keyword_tracking_pool_sessions.phone_number_id = phone_numbers.id
                                            AND keyword_tracking_pool_sessions.ended_at IS NULL
                                    ) AS active_assignments')
                                ])
                                ->where('keyword_tracking_pool_id', $this->id)
                                ->orderBy('active_assignments', 'ASC')
                                ->orderBy('last_assigned_at', 'ASC')
                                ->orderBy('id', 'ASC');

        $phoneNumber = $query->first();
        
        if( ! $phoneNumber ) return null;

        $phoneNumber->last_assigned_at = now()->format('Y-m-d H:i:s.u');
        $phoneNumber->total_assignments++;
        $phoneNumber->save();

        return $phoneNumber;
    }

    public function activeSessions($phoneNumberId = null, $contactId = null, $excludeClaimed = true)
    {
        $query = KeywordTrackingPoolSession::where('keyword_tracking_pool_id', $this->id)
                                           ->whereNull('ended_at');
        if( $phoneNumberId ){
            $query->where('phone_number_id', $phoneNumberId);
        }

        if( $contactId ){
            $query->where(function($query) use($contactId, $excludeClaimed){
                $query->where('contact_id', $contactId);
                if( $excludeClaimed )
                    $query->orWhereNull('contact_id');
            });
            $query->orderBy('contact_id', 'DESC');
        }else{
            if( $excludeClaimed )
                $query->whereNull('contact_id');

            $query->orderBy('updated_at', 'DESC');
            $query->orderBy('created_at', 'DESC');
        }
        
        return $query->get();
    }
}
