<?php

namespace App\Models\Company;

use Illuminate\Http\File;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Traits\HandlesStorage;
use \GuzzleHttp\Client;
use Twilio\Rest\Client as TwilioClient;
use App;
use Storage;
use Exception;
use Log;

class CallRecording extends Model
{
    use HandlesStorage, SoftDeletes;

    protected $fillable = [
        'call_id',
        'external_id',
        'path',
        'duration',
        'file_size'
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by'
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
        return rtrim(config('app.cdn_url'), '/') 
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
            $client   = new \GuzzleHttp\Client();
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
        }catch(Exception $e){
            Log::error($e->getTraceAsString());
        }
    }

    public function deleteRemoteResource()
    {
        Storage::delete($this->path);
    }
}
