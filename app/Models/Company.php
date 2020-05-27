<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Contracts\Exportable;
use \App\Traits\PerformsExport;
use \App\Models\UserCompany;
use \App\Models\Company\BlockedPhoneNumber;
use \App\Models\Company\BlockedPhoneNumber\BlockedCall;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumberConfig;
use \App\Jobs\BatchDeleteAudioJob;
use \App\Jobs\BatchDeletePhoneNumbersJob;
use \App\Jobs\BatchDeleteCallRecordingsJob;

use Exception;
use DB;

class Company extends Model implements Exportable
{
    use SoftDeletes, PerformsExport;

    protected $table = 'companies'; 

    protected $fillable = [
        'account_id',
        'name',
        'industry',
        'country',
        'tts_voice',
        'tts_language',
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
            'id'        => 'Id',
            'name'      => 'Name',
            'industry'  => 'Industry',
            'country'   => 'Country',
            'created_at_local'=> 'Created'
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
                            'phone_number_pools.id AS phone_number_pool_id',
                            DB::raw("DATE_FORMAT(CONVERT_TZ(companies.created_at, 'UTC','" . $user->timezone . "'), '%b %d, %Y') AS created_at_local") 
                        ])
                        ->leftJoin('phone_number_pools', 'phone_number_pools.company_id', 'companies.id')
                        ->where('companies.account_id', $user->account_id);

        if( ! $user->canViewAllCompanies() )
            $query->whereIn('companies.id', function($query) use($user){
                $query->select('company_id')
                        ->from('user_companies')
                        ->where('user_id', $user->id);
            });

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

    public function account()
    {
        return $this->belongsTo('\App\Models\Account');
    }

    public function purge($user)
    {    
        Call::where('company_id', $this->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);
        
        UserCompany::where('company_id', $this->id)->delete();

        PhoneNumberPool::where('company_id', $this->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        PhoneNumberConfig::where('company_id', $this->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        BlockedCall::whereIn('blocked_phone_number_id', function($q){
                        $q->select('id')
                            ->from('blocked_phone_numbers')
                            ->where('blocked_phone_numbers.company_id', $this->id);
                    })->delete();

        BlockedPhoneNumber::where('company_id', $this->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        ReportAutomation::whereIn('report_id', function($q){
                            $q->select('id')
                                ->from('reports')
                                ->where('reports.company_id', $this->id);
                        })->delete();

        Report::where('company_id', $this->id)->update([
            'deleted_by' => $user->id,
            'deleted_at' => now()
        ]);

        //
        //  Batch delete items with remote resources
        //
        BatchDeletePhoneNumbersJob::dispatch($user, $this);
        BatchDeleteAudioJob::dispatch($user, $this);
        BatchDeleteCallRecordingsJob::dispatch($user, $this);
    }
}
