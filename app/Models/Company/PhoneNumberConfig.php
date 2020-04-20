<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use App\Models\Company\PhoneNumberPool;
use \App\Traits\PerformsExport;
use DB;

class PhoneNumberConfig extends Model
{
    use SoftDeletes, PerformsExport;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'forward_to_number',
        'greeting_audio_clip_id',
        'greeting_message',
        'whisper_message',
        'keypress_key',
        'keypress_timeout',
        'keypress_attempts',
        'keypress_audio_clip_id',
        'keypress_message',
        'recording_enabled_at',
        'caller_id_enabled_at',
        'keypress_enabled_at'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    /**
     * Relationships
     * 
     */
    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    /**
     *   Attributes 
     *
     */
    public function getLinkAttribute()
    {
        return route('read-phone-number-config', [
            'companyId'           => $this->company_id,
            'phoneNumberConfigId' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'PhoneNumberConfig';
    }

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'name'              => 'Name',
            'forward_to_number' => 'Forwarding Number',
            'created_at'        => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Numbers Configurations - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return PhoneNumberConfig::where('company_id', $input['company_id']);
    }


    public function isInUse()
    {
        if( PhoneNumberPool::where('phone_number_config_id', $this->id)->count() )
            return true;

        if( PhoneNumber::where('phone_number_config_id', $this->id)->count() )
            return true;

        return false;
    }

    public function forwardToPhoneNumber()
    {
        $number = $this->forward_to_number;
        if( strlen($number) > 10 )
            $number = '+' . $number;
            
        return $number;
    }

    public function greetingMessage(Call $call)
    {
        if( ! $this->greeting_message )
            return null;

        return $this->message($this->greeting_message, $call);
    }

    public function whisperMessage(Call $call)
    {
        if( ! $this->whisper_message )
        return null;
        
        return $this->message($this->whisper_message, $call);
    }

    public function message(string $message, Call $call)
    {
        $variables = [
            '${source}'             => $call->source,
            '${medium}'             => $call->medium,
            '${content}'            => $call->content,
            '${campaign}'           => $call->campaign,
            '${caller_first_name}'  => $call->caller_first_name,
            '${caller_last_name}'   => $call->caller_last_name,
            '${caller_country_code}'=> $call->from_country_code,
            '${caller_number}'      => $call->from_number,
            '${caller_city}'        => $call->from_city,
            '${caller_state}'       => $call->from_state,
            '${caller_zip}'         => $call->from_zip,
            '${caller_country}'     => $call->from_country,
            '${caller_network}'     => $call->from_network,
            '${dialed_number}'      => $call->to_number
        ];

        return str_replace(array_keys($variables), array_values($variables), strtolower($message));
    }
    
}
