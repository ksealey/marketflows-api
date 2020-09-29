<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Contracts\Exportable;
use \App\Models\Company\Report;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberConfig;
use \App\Jobs\BatchDeleteAudioJob;
use \App\Jobs\BatchDeletePhoneNumbersJob;
use \App\Jobs\BatchDeleteCallRecordingsJob;

use Exception;
use DB;

class Company extends Model implements Exportable
{
    use SoftDeletes;

    protected $table = 'companies'; 

    protected $fillable = [
        'account_id',
        'name',
        'industry',
        'country',
        'tts_voice',
        'tts_language',
        'ga_id',
        'created_by',
        'updated_by'
    ];

    protected $hidden = [
        'account_id',
        'user_id',
        'deleted_at',
        'deleted_by'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    static public function exports() : array
    {
        return [
            'id'                => 'Id',
            'name'              => 'Name',
            'industry'          => 'Industry',
            'country'           => 'Country',
            'ga_id'             => 'Google Analytics Id',
            'created_at_local'  => 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Companies';
    }

    static public function exportQuery($user, array $input)
    {
        $query = Company::select([
                    'companies.*', 
                    DB::raw("DATE_FORMAT(CONVERT_TZ(companies.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local") 
                ])
                ->where('companies.account_id', $user->account_id);

        return $query;
    }

    public function getLinkAttribute()
    {
        return route('read-company', [
            'company' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'Company';
    }

    public function getCompanyCodeAttribute()
    {
        $countryCodes = config('app.country_codes');
        
        return $countryCodes[$this->country] ?? null;
    }


    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }
}
