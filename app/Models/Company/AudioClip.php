<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company;

class AudioClip extends Model
{
    use SoftDeletes;

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

    static public function fullStoragePath(Company $company)
    {
        return 'cdn/' . self::storagePath($company);
    }

    static public function storagePath(Company $company)
    {
        return 'accounts/' 
                . $company->account_id 
                . '/companies/' 
                . $company->id 
                . '/audio_clips';
    }

    public function getURL()
    {
        return rtrim(env('CDN_URL'), '/') 
                . '/' 
                . trim($this->path, '/');
    }
}
