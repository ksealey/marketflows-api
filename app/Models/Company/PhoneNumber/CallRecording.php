<?php

namespace App\Models\Company\PhoneNumber;

use Illuminate\Database\Eloquent\Model;
use \App\Traits\HandlesStorage;
use \App\Traits\HandlesPhoneNumbers;


class CallRecording extends Model
{
    use HandlesStorage, HandlesPhoneNumbers;

    protected $fillable = [
        'call_id',
        'external_id',
        'path',
        'duration'
    ];
   
    public function getURL()
    {
        return rtrim(env('CDN_URL'), '/') 
                . '/' 
                . trim($this->path, '/');
    }

    static public function deleteRemoteRecording($recordingSid)
    {
        if( self::$testing )
            return true;

        $client = self::client();

        $client->recordings($recordingSid)
               ->delete();
    }
}
