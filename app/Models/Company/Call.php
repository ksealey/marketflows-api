<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;

class Call extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'account_id',
        'company_id',
        'contact_id',
        'type',
        'category',
        'sub_category',

        'phone_number_id',
        'phone_number_name',

        'keyword_tracking_pool_id',
        'keyword_tracking_pool_name',
        'keyword_tracking_pool_session_id',

        'external_id',
        'direction',
        'status',
        'duration',
        'first_call',

        'source',
        'medium',
        'content',
        'campaign',
        'keyword',

        'is_organic',
        'is_paid',
        'is_direct',
        'is_search',
        'is_referral',
        'is_remarketing',

        'recording_enabled',
        'transcription_enabled',

        'forwarded_to',

        'cost',

        'lead_status',
        'converted_at',

        'created_at',
        'updated_at'
    ];

    protected $hidden = [
        'external_id',
        'deleted_at',
        'deleted_by',
        'cost'
    ];

    protected $appends = [
        'link',
        'kind',
    ];

    protected $casts = [
        'recording_enabled'     => 'boolean',
        'transcription_enabled' => 'boolean',
        'first_call'            => 'boolean',
        'is_paid'               => 'boolean',
        'is_organic'            => 'boolean',
        'is_referral'           => 'boolean',
        'is_remarketing'        => 'boolean',
        'is_direct'             => 'boolean',
        'is_search'             => 'boolean'
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';  

    static public function exports() : array
    {
        return [
            'id'                    => 'Id',
            'company_name'          => 'Company',
            'caller_name'           => 'Caller',
            'caller_country_code'   => 'Caller Country Code',
            'caller_number'         => 'Caller Number',
            'phone_number_name'     => 'Tracking Number',
            'status'                => 'Status',
            'created_at_local'      => 'Call Time',
            'duration'              => 'Duration (Seconds)',
            'category'              => 'Category',
            'sub_category'          => 'Sub-Category',
            'source'                => 'Source',
            'medium'                => 'Medium',
            'campaign'              => 'Campaign',
            'content'               => 'Content',
            'keywords'              => 'Keyword',
            'is_paid'               => 'Paid',
            'is_organic'            => 'Organic',
            'is_direct'             => 'Direct',
            'is_search'             => 'Search',
            'is_referral'           => 'Referral',
            'is_remarketing'        => 'Remarketing',
            'recording_enabled'     => 'Recording Enabled',
            'transcription_enabled' => 'Transcription Enabled',
            'first_call'            => 'First Call',
            
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Calls - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        $query = Call::select([
                    'calls.*', 
                    DB::raw(
                        'CASE WHEN phone_numbers.keyword_tracking_pool_id IS NOT NULL
                            THEN keyword_tracking_pools.name
                        ELSE
                            phone_numbers.name
                        END AS phone_number_name'
                    ),
                    'companies.id AS company_id',
                    'companies.name AS company_name',
                    DB::raw(
                        'CASE
                            WHEN call_recordings.path IS NOT NULL AND call_recordings.deleted_at IS NULL
                                THEN CONCAT(\'' . config('app.cdn_url') . '/' . '\', TRIM(BOTH \'\/\' FROM call_recordings.path))
                            ELSE NULL
                        END
                        AS recording_url'
                    ),
                    DB::raw(
                        "CASE
                            WHEN call_recordings.path IS NOT NULL AND call_recordings.deleted_at IS NULL
                                THEN 'audio/mp3'
                            ELSE NULL
                        END
                        AS recording_mimetype"
                    ),
                    DB::raw('CONCAT(phone_numbers.country_code,phone_numbers.number) AS phone_number'),
                    DB::raw('TRIM(CONCAT(contacts.first_name, \' \', contacts.last_name)) AS caller_name'),
                    DB::raw("contacts.country_code AS caller_country_code"),
                    DB::raw("contacts.number AS caller_number"),
                    DB::raw("DATE_FORMAT(CONVERT_TZ(calls.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y %r') AS created_at_local")
                ])
                ->where('calls.company_id', $input['company_id']);

        if( !empty($input['phone_number_id']) ){
            $query->where('phone_number_id', $input['phone_number_id']);
        }

        $query->leftJoin('call_recordings', function($join){
            $join->on('call_recordings.call_id', 'calls.id')
                 ->whereNull('call_recordings.deleted_at');
        });

        //  Join non-deleted numbers
        $query->leftJoin('phone_numbers', function($join){
            $join->on('calls.phone_number_id', 'phone_numbers.id')
                ->whereNull('phone_numbers.deleted_at');
        });

        $query->leftJoin('keyword_tracking_pools', function($join){
            $join->on('calls.keyword_tracking_pool_id', 'keyword_tracking_pools.id')
                ->whereNull('keyword_tracking_pools.deleted_at');
        });

        //  Join non-deleted companies
        $query->leftJoin('companies', function($join){
            $join->on('calls.company_id', 'companies.id')
                ->whereNull('companies.deleted_at');
        });

        //  Join non-deleted contacts
        $query->leftJoin('contacts', function($join){
            $join->on('contacts.id', 'calls.contact_id')
                ->whereNull('contacts.deleted_at');
        });

        return $query;
    }

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function contact()
    {
        return $this->belongsTo('\App\Models\Company\Contact');
    }

    public function phone_number()
    {
        return $this->belongsTo('\App\Models\Company\PhoneNumber');
    }

    public function recording()
    {
        return $this->hasOne('\App\Models\Company\CallRecording');
    }

    public function session()
    {
        return $this->belongsTo('\App\Models\Company\KeywordTrackingPoolSession');
    }
    
    public function getLinkAttribute()
    {
        return route('read-call', [
            'company' => $this->company_id,
            'call'    => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'Call';
    }
}
