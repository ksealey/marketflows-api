<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use DB;

class PhoneNumberConfig extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'account_id',
        'company_id',
        
        'name',
        'forward_to_number',

        'greeting_enabled',
        'greeting_message_type',
        'greeting_message',
        'greeting_audio_clip_id',

        'keypress_enabled',
        'keypress_key',
        'keypress_timeout',
        'keypress_attempts',
        'keypress_message_type',
        'keypress_audio_clip_id',
        'keypress_message',

        'whisper_enabled',
        'whisper_message',

        'recording_enabled',

        'transcription_enabled',

        'created_by',
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
            'created_at_local'  => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Numbers Configurations - ' . $input['company_name'];
    }

    static public function exportQuery($user, array $input)
    {
        return PhoneNumberConfig::select([
                                    'phone_number_configs.*',
                                    DB::raw("DATE_FORMAT(CONVERT_TZ(phone_numbers.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local")
                                ])
                                ->where('company_id', $input['company_id']);
    }


    public function isInUse()
    {
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
        $contact = $call->contact;

        $variables = [
            '${source}'             => $call->source,
            '${medium}'             => $call->medium,
            '${content}'            => $call->content,
            '${campaign}'           => $call->campaign,
            '${caller_first_name}'  => $contact->first_name,
            '${caller_last_name}'   => $contact->last_name,
            '${caller_country_code}'=> $contact->country_code,
            '${caller_number}'      => $contact->number,
            '${caller_city}'        => $contact->city,
            '${caller_state}'       => $contact->state,
            '${caller_zip}'         => $contact->zip,
            '${caller_country}'     => $contact->country,
            '${dialed_number}'      => $call->forwarded_to
        ];

        return str_replace(array_keys($variables), array_values($variables), strtolower($message));
    }
    
}
