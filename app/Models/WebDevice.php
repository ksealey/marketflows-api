<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebDevice extends Model
{
    protected $fillable = [
        'uuid',
        'web_profile_id',
        'fingerprint',
        'ip',
        'width',
        'height',
        'type',
        'brand',
        'os',
        'os_version',
        'browser',
        'browser_version',
        'browser_engine',
    ];

    public function getFingerprint()
    {
        return ! empty($this->fingerprint) ? $this->fingerprint : sha1(
            $this->type .
            $this->brand .
            $this->os .
            $this->os_version . 
            $this->browser .
            $this->ip
        );
    }
}
