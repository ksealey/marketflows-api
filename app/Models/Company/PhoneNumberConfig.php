<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\PhoneNumber;
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
        'recording_enabled_at'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $appends = [
        'audio_clip',
        'phone_numbers',
        'phone_number_pools',
        'link',
        'kind'
    ];

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
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

    public function recordingEnabled()
    {
        return $this->recording_enabled_at ? true : false;
    }

    public function source()
    {
        return $this->source;
    }

    public function audioClipId()
    {
        return $this->audio_clip_id;
    }

    public function getAudioClipAttribute()
    {
        if( ! $this->audio_clip_id )
            return null;

        return AudioClip::where('id', $this->audio_clip_id)->first();
    }

    public function getPhoneNumbersAttribute()
    {
        return PhoneNumber::where('phone_number_config_id', $this->id)->get();
    }

    /**
     * Get the associated phone number pools
     * 
     */
    public function getPhoneNumberPoolsAttribute()
    {
        return PhoneNumberPool::where('phone_number_config_id', $this->id)->get();
    }

    /**
     * Get the link
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-phone-number-config', [
            'companyId'           => $this->company_id,
            'phoneNumberConfigId' => $this->id
        ]);
    }

    /**
     * Get the kind
     * 
     */
    public function getKindAttribute()
    {
        return 'PhoneNumberConfig';
    }
}
