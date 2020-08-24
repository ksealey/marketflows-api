<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;
use App\Models\Company\PhoneNumberConfig;
use \App\Traits\HandlesStorage;
use Storage;

class AudioClip extends Model
{
    use SoftDeletes, HandlesStorage;

    protected $fillable = [
        'account_id',
        'company_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'name',
        'path',
        'mime_type',
    ];

    protected $hidden = [
        'path',
        'deleted_at',
        'deleted_by'
    ];

    protected $appends = [
        'link',
        'kind',
        'url'
    ];

    public function getLinkAttribute()
    {
        return route('read-audio-clip', [
            'company'     => $this->company_id,
            'audioClip'   => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'AudioClip';
    }

    public function getUrlAttribute()
    {
        return trim(config('app.cdn_url'), '/') . '/' . trim($this->path, '/');
    }

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function deleteRemoteResource()
    {
        Storage::delete($this->path);
    }

    public function isInUse()
    {
        return PhoneNumberConfig::where('greeting_audio_clip_id', $this->id)
                                ->orWhere('keypress_audio_clip_id', $this->id)
                                ->count() ? true : false;
    }
}
