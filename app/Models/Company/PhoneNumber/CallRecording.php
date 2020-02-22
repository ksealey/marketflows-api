<?php

namespace App\Models\Company\PhoneNumber;

use Illuminate\Http\File;
use Illuminate\Database\Eloquent\Model;
use \App\Traits\HandlesStorage;
use \GuzzleHttp\Client;
use Twilio\Rest\Client as TwilioClient;
use App;
use Storage;
use Exception;

class CallRecording extends Model
{
    use HandlesStorage;

    protected $fillable = [
        'call_id',
        'external_id',
        'path',
        'duration'
    ];

    protected $appends = [
        'url',
        'kind'
    ];

    /**
     * Appends
     * 
     */
    public function getUrlAttribute()
    {
        return rtrim(env('CDN_URL'), '/') 
                . '/' 
                . trim($this->path, '/');
    }
   
    public function getKindAttribute()
    {
        return 'CallRecording';
    }



    static public function moveRecording($url, $recordingSid, $accountId, $companyId)
    {
        //  Download file
        $content = self::downloadRecording($url);

        if( ! $content )
            return null;

        //  Store remotely
        $remotePath = self::storeRecording($content, $accountId, $companyId, $recordingSid);
        if( ! $remotePath )
            return null;

        //  Remove remote file
        self::deleteRemoteFile($recordingSid);

        return $remotePath;
    }

    static public function downloadRecording($url)
    {
        try{
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url);

            return $response->getBody();
        }catch(Exception $e){
            return null;
        }
    }

    static public function storeRecording($content, $accountId, $companyId, $recordingSid)
    {
        $storagePath = trim(CallRecording::storagePath($accountId, $companyId, 'call_recordings'), '/');
        $path        = $storagePath . '/' . $recordingSid;

        Storage::put($path, $content);

        return $path;
    }

    

    static public function deleteRemoteFile($recordingSid)
    {
        $config = config('services.twilio');

        $twilio = new TwilioClient($config['sid'], $config['token']);

        try{
            $twilio->recordings($recordingSid)
                    ->delete();
        }catch(Exception $e)
        {}
    }
}
