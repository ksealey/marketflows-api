<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;
use \App\Traits\HandlesStorage;

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
        'deleted_at'
    ];

    public function canBeDeleted()
    {
        // 
        // TODO: Make sure this audio clip is not attached to phone numbers
        // ... 
        //
        return true;
    }

    public function getURL()
    {
        return rtrim(env('CDN_URL'), '/') 
                . '/' 
                . trim($this->path, '/');
    }
}
