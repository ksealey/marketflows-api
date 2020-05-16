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
        'account_id',
        'company_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'name',
        'forward_to_number',
        'greeting_enabled',
        'greeting_audio_clip_id',
        'greeting_message',
        'whisper_message',
        'keypress_key',
        'keypress_timeout',
        'keypress_attempts',
        'keypress_audio_clip_id',
        'keypress_message',
        'recording_enabled',
        'keypress_enabled'
    ];

    protected $hidden = [
        'deleted_by',
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
            'company'           => $this->company_id,
            'phoneNumberConfig' => $this->id
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
            return '';

        return $this->variableMessage($this->greeting_message, $call);
    }

    public function keypressMessage(Call $call)
    {
        if( ! $this->keypress_message )
            return '';

        return $this->variableMessage($this->keypress_message, $call);
    }

    public function whisperMessage(Call $call)
    {
        if( ! $this->whisper_message )
            return '';
        
        return $this->variableMessage($this->whisper_message, $call);
    }

    public function variableMessage(string $message, Call $call)
    {
        $variables = [
            '${source}'             => $call->source,
            '${medium}'             => $call->medium,
            '${content}'            => $call->content,
            '${campaign}'           => $call->campaign,
            '${caller_name}'        => $call->caller_name,
            '${caller_country_code}'=> $call->caller_country_code,
            '${caller_number}'      => $call->caller_number,
            '${caller_city}'        => $call->caller_city,
            '${caller_state}'       => $call->caller_state,
            '${caller_zip}'         => $call->caller_zip,
            '${caller_country}'     => $call->caller_country,
            '${dialed_number}'      => $call->forwarded_to
        ];

        return str_replace(array_keys($variables), array_values($variables), strtolower($message));
    }
    
}
