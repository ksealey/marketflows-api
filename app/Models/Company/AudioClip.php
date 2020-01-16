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
        'created_by',
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

    public function getURL($company = null)
    {
        $tempURL = Storage::temporaryUrl(
            $this->path, now()->addMinutes(60)
        );

        //
        //  TODO: Store in cache as HOT data for 55 minutes
        //  ...
        //  

        return $tempURL;
    }

    public function getUrlAttribute()
    {
        return $this->getURL();
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
