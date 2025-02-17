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
use \App\Models\Company\KeywordTrackingPool;
use \App\Models\Company\PhoneNumberConfig;
use \App\Models\Plugin;
use \App\Models\Company\CompanyPlugin;
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
        'source_param',
        'medium_param',
        'content_param',
        'campaign_param',
        'keyword_param',
        'tracking_expiration_days',
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

    public function getPluginsAttribute()
    {
        return CompanyPlugin::select([
                                'company_plugins.*',
                                'plugins.name',
                                'plugins.details',
                                'plugins.image_path',
                                'plugins.price'
                            ])
                            ->where('company_plugins.company_id', $this->id)
                            ->leftJoin('plugins', 'plugins.key', 'company_plugins.plugin_key')
                            ->get();
    }

    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    public function keyword_tracking_pool()
    {
        return $this->hasOne('\App\Models\Company\KeywordTrackingPool');
    }

    public function detached_phone_numbers()
    {
        return $this->hasMany('\App\Models\Company\PhoneNumber')
                    ->whereNull('keyword_tracking_pool_id')
                    ->whereNull('deleted_at')
                    ->orderBy('id', 'DESC');
    }
}
