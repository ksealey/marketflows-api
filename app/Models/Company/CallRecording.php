<?php

namespace App\Models\Company;

use Illuminate\Http\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Traits\HandlesStorage;
use \GuzzleHttp\Client;
use Twilio\Rest\Client as TwilioClient;
use App;
use Storage;
use Exception;
use Log;

class CallRecording extends Model
{
    use HandlesStorage, SoftDeletes;

    protected $fillable = [
        'call_id',
        'external_id',
        'path',
        'duration',
        'file_size',
        'transcription_path'
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by'
    ];

    protected $appends = [
        'url',
        'kind',
        'mimetype'
    ];

    /**
     * Appends
     * 
     */
    public function getUrlAttribute()
    {
        return rtrim(config('app.cdn_url'), '/') 
                . '/' 
                . trim($this->path, '/');
    }

    public function getStorageUrlAttribute()
    {
        return rtrim(config('app.storage_url'), '/') 
                . '/' 
                . trim($this->path, '/');
    }
   
    public function getKindAttribute()
    {
        return 'CallRecording';
    }

    public function getMimeTypeAttribute()
    {
        return 'audio/mpeg';
    }

    /**
     * Relationships
     * 
     */
    public function transcription()
    {
        return $this->hasOne('\App\Models\Company\Transcription');
    }
}
