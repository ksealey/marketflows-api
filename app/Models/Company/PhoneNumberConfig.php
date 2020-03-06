<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\PhoneNumber;
use App\Models\Company\Call;
use App\Models\Company\PhoneNumberPool;
use DB;

class PhoneNumberConfig extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'forward_to_country_code',
        'forward_to_number',
        'greeting_audio_clip_id',
        'greeting_message',
        'whisper_message',
        'recording_enabled_at',
        'caller_id_enabled_at'
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

    public function getPhoneNumberPoolsAttribute()
    {
        return PhoneNumberPool::where('phone_number_config_id', $this->id)->get();
    }

    public function getPhoneNumbersAttribute()
    {
        return PhoneNumber::where('phone_number_config_id', $this->id)->get();
    }

    public function isInUse()
    {
        if( count($this->phone_number_pools) )
            return true;

        if( count($this->phone_numbers) )
            return true;

        return false;
    }

    public function forwardToPhoneNumber()
    {
        return  ($this->forward_to_country_code ? '+' . $this->forward_to_country_code : '') 
                . $this->forward_to_number;
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
