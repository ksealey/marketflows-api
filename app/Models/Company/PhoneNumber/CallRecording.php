<?php

namespace App\Models\Company\PhoneNumber;

use Illuminate\Http\File;
use Illuminate\Database\Eloquent\Model;
use \App\Traits\HandlesStorage;
use \App\Traits\HandlesPhoneNumbers;
use App;
use Storage;

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

    static public function moveRecording($url, $recordingSid, $company)
    {
        //  Download file
        $localPath = self::downloadRecording($url);

        //  Store remotely
        $remotePath = self::storeRecording($localPath, $company);

        //  Remote file residue
        self::cleanup($localPath, $recordingSid);

        return $remotePath;
    }

    static public function downloadRecording($url)
    {
        $localPath = storage_path() . '/' . str_random(40);
        
        if( App::environment(['prod', 'production']) ){
            $data = file_get_contents($url); 
        }else{
            $data = str_random(40);
        }

        file_put_contents($localPath, $data);

        return $localPath;
    }

    static public function storeRecording($localPath, $company)
    {
        $file        = new File($localPath);
        $storagePath = trim(CallRecording::storagePath($company, 'call_recordings'), '/');
        $fileName    = str_random(32) . '.' . $file->guessExtension();

        Storage::putFileAs($storagePath, $file, $fileName);

        return $storagePath . '/' . $fileName;
    }

    static public function cleanup($localPath, $recordingSid)
    {
        if( App::environment(['prod', 'production']) ){
            $client = self::client();

            $client->recordings($recordingSid)
                   ->delete();
        }

        if( file_exists($localPath) )
            unlink($localPath);
    }
}
