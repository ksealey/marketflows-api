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
        'duration',
        'file_size'
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

    static public function moveRecording($url, $recordingSid, $recordingDuration, $call)
    {
        //  Download file
        $content = self::downloadRecording($url . '.mp3');

        if( ! $content )
            return null;

        //  Store remotely
        $remotePath = self::storeRecording($content, $call);
        if( ! $remotePath )
            return null;

        //  Remove remote file
        self::deleteRemoteFile($recordingSid);

        return CallRecording::create([
            'call_id'       => $call->id,
            'external_id'   => $recordingSid,
            'path'          => $remotePath,
            'duration'      => intval($recordingDuration),
            'file_size'     => strlen($content)
        ]);
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

    static public function storeRecording($content, $call)
    {
        $storagePath = trim(CallRecording::storagePath($call->account_id, $call->company_id, 'recordings'), '/');
        $path        = $storagePath . '/Call-' . $call->id;

        Storage::put($path, $content, 'public');

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
