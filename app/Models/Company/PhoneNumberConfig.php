<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\PhoneNumber;
use App\Models\Company\PhoneNumberPool;

class PhoneNumberConfig extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'source',
        'forward_to_country_code',
        'forward_to_number',
        'audio_clip_id',
        'recording_enabled_at',
        'whisper_message',
        'whisper_language',
        'whisper_voice'
    ];

    protected $hidden = [
        'company_id',
        'created_by',
        'deleted_at'
    ];

    public function isInUse()
    {
        if( PhoneNumber::where('phone_number_config_id', $this->id)->count() )
            return true;
    
        if( PhoneNumberPool::where('phone_number_config_id', $this->id)->count() )
            return true;

        return false;
    }
}
