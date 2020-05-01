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
            'companyId'     => $this->company_id,
            'audioClipId'   => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'AudioClip';
    }

    public function getUrlAttribute()
    {
        return env('CDN_URL') . '/' . $this->path;
    }

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

    public function isInUse()
    {
        return PhoneNumberConfig::where('audio_clip_id', $this->id)->count() ? true : false;
    }
}
