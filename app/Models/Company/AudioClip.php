<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;
use \App\Traits\HandlesStorage;
use Storage;

class AudioClip extends Model
{
    use SoftDeletes, HandlesStorage;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'path',
        'mime_type'
    ];

    protected $hidden = [
        'path',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind',
        'url'
    ];

    public function canBeDeleted()
    {
        // 
        // TODO: Make sure this audio clip is not attached to phone numbers
        // ... 
        //
        return true;
    }
    
    public function getUrlAttribute()
    {
        return env('CDN_URL') . '/' . $this->path;
    }

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }

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
}
