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
    
    protected $fillable = [
        'uuid',
        'account_id',
        'company_id',
        'first_name',
        'last_name',
        'email',
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
        'deleted_at',
        'deleted_by'
    ];

    protected $appends = [
        'kind',
        'link'
    ];

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'company_id'        => 'Company Id',
            'first_name'        => 'First Name',
            'last_name'         => 'Last Name',
            'country_code'      => 'Country Code',
            'number'            => 'Number',
            'email'             => 'Email',
            'city'              => 'City',
            'state'             => 'State',
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
                            DB::raw("DATE_FORMAT(CONVERT_TZ(created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local")
                        ])
                        ->where('contacts.company_id', $input['company_id']);
    }

    public function sessions()
    {
        return $this->hasMany(KeywordTrackingPoolSession::class)
                    ->orderBy('updated_at', 'DESC');
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

    public function getActivityAttribute()
    {
        $calls = Call::where('contact_id', $this->id)
                     ->orderBy('created_at', 'ASC')
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

        $activities = array_merge([
            [
                'kind'       => 'ActivityCreateContact',
                'created_at' => $this->created_at
            ],
        ], $calls->toArray());

        //
        //  Order by create date, asc
        //    
        usort($activities, function($a, $b){
            return $a['created_at'] <= $b['created_at'] ? -1 : 1;
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
