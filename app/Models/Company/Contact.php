<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\KeywordTrackingPoolSession;
use DB;

class Contact extends Model
{
    use SoftDeletes;
    
    const CREATE_METHOD_INBOUND_CALL = 'Inbound Call';
    const CREATE_METHOD_MANUAL       = 'Manual';
    
    protected $fillable = [
        'uuid',
        'account_id',
        'company_id',
        'create_method',
        'first_name',
        'last_name',
        'country_code',
        'number',
        'city',
        'state',
        'zip',
        'country',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $hidden = [
        'email',
        'deleted_at',
        'deleted_by'
    ];

    protected $appends = [
        'kind',
        'link',
        'formatted_name',
        'formatted_number'
    ];

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'company_name'      => 'Company',
            'first_name'        => 'First Name',
            'last_name'         => 'Last Name',
            'country_code'      => 'Country Code',
            'number'            => 'Number',
            'call_count'        => 'Calls',
            'last_call_at_local' => 'Last Call',
            'city'              => 'City',
            'state'             => 'State/Province',
            'zip'               => 'Zip',
            'country'           => 'Country',
            'created_at_local'  => 'Created',
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Contacts - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return Contact::select([
                            'contacts.*',
                            'contact_call_count.call_count',
                            'contact_last_call_at.last_call_at',
                            DB::raw("DATE_FORMAT(CONVERT_TZ(last_call_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y %r') AS last_call_at_local"),
                            'companies.name as company_name',
                            DB::raw("DATE_FORMAT(CONVERT_TZ(contacts.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y %r') AS created_at_local")
                        ])
                        ->leftJoin('companies', 'companies.id', 'contacts.company_id')
                        ->leftJoin('contact_call_count', 'contact_call_count.contact_id', 'contacts.id')
                        ->leftJoin('contact_last_call_at', 'contact_last_call_at.contact_id', 'contacts.id')
                        ->where('contacts.company_id', $input['company_id']);
    }

    public function getSessionsAttribute()
    {
        $sessions = KeywordTrackingPoolSession::select([
                                                    'keyword_tracking_pool_sessions.*',
                                                    'keyword_tracking_pools.name AS keyword_tracking_pool_name',
                                                    'phone_numbers.name AS phone_number_name'
                                                ])
                                              ->where('contact_id', $this->id)
                                              ->leftJoin('keyword_tracking_pools', 'keyword_tracking_pools.id', '=','keyword_tracking_pool_sessions.keyword_tracking_pool_id')
                                              ->leftJoin('phone_numbers', 'phone_numbers.id', '=','keyword_tracking_pool_sessions.phone_number_id')
                                              
                                              ->orderBy('updated_at', 'DESC')
                                              ->get();
        return $sessions;
    }

    public function activeSession($phoneNumberId = null)
    {
        $query =  KeywordTrackingPoolSession::whereNull('ended_at')
                                        ->where('contact_id', $this->id)
                                        ->orderBy('updated_at', 'DESC')
                                        ->orderBy('created_at', 'DESC');

        if( $phoneNumberId ){
            $query->where('phone_number_id', $phoneNumberId);
        }
        
        return $query->first();
    }

    public function hasCalled(PhoneNumber $phoneNumber)
    {
        return Call::where('contact_id', $this->id)
                    ->where('phone_number_id', $phoneNumber->id)
                    ->count() ? true : false;  
    }

    public function getKindAttribute()
    {
        return 'Contact';
    }

    public function getLinkAttribute()
    {
        return route('read-contact', [
            'company' => $this->company_id,
            'contact' => $this->id
        ]);
    }

    public function getFormattedNameAttribute()
    {
        return trim($this->first_name . ' ' . ($this->last_name ?: ''));
    }

    public function getFormattedNumberAttribute()
    {
        return $this->e164PhoneFormat();
    }

    public function getActivityAttribute()
    {
        $calls = Call::where('contact_id', $this->id)
                     ->orderBy('created_at', 'DESC')
                     ->get();

        //
        //  Attach recordings
        //
        $recordingMap = [];
        if( count($calls) ){
            $callIds        = array_column($calls->toArray(), 'id');
            CallRecording::whereIn('call_id', $callIds)
                            ->get()
                            ->each(function($recording) use(&$recordingMap){
                                $recording->link = route('read-call-recording', [
                                    'company' => $this->company_id,
                                    'call'    => $recording->call_id
                                ]);
                                $recordingMap[$recording->call_id] = $recording;
                            });
        }

        foreach( $calls as $call ){
            $call->recording = $recordingMap[$call->id] ?? null;
        }

        $activities = array_merge($calls->toArray(), [
            [
                'id'         => $this->id,
                'kind'       => 'ActivityCreateContact',
                'created_at' => $this->created_at
            ],
        ]);

        //
        //  Add sessions
        //
        $sessions = $this->sessions;
        $activities = array_merge($sessions->toArray(), $activities);

        //
        //  Order by create date, desc
        //    
        usort($activities, function($a, $b){
            return $a['created_at'] >= $b['created_at'] ? -1 : 1;
        });

        return [
            'items'      => $activities,
            'call_count' => count($calls)
        ];
    }

    public function fullPhone()
    {
        return $this->country_code . $this->number;
    }

    public function e164PhoneFormat()
    {
        return  ($this->country_code ? '+' . $this->country_code : '') 
                . $this->number;
    }
}
