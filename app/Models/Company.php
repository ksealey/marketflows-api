<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Contracts\Exportable;
use \App\Traits\PerformsExport;
use \App\Models\UserCompany;
use \App\Models\BlockedPhoneNumber;
use \App\Models\BlockedPhoneNumber\BlockedCall;
use \App\Models\Company\Report;
use \App\Models\Company\ReportAutomation;
use \App\Models\Company\AudioClip;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\PhoneNumber;
use \App\Models\Company\PhoneNumberPool;
use \App\Models\Company\PhoneNumberConfig;

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
            'created_at'=> 'Created'
        ];
    }

    static public function exportFileName($user, array $input) : string
    {
        return 'Companies';
    }

    static public function exportQuery($user, array $input)
    {
        return Company::select(['companies.*', 'phone_number_pools.id AS phone_number_pool_id'])
                        ->leftJoin('phone_number_pools', 'phone_number_pools.company_id', 'companies.id')
                        ->where('companies.account_id', $user->account_id)
                        ->whereIn('companies.id', function($query) use($user){
                            $query->select('company_id')
                                    ->from('user_companies')
                                    ->where('user_id', $user->id);
                        })
                        ->whereNull('companies.deleted_at');
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

    public function purge()
    {
        //  Remove audio clips along with their remote resource
        AudioClip::where('company_id', $this->id)
                    ->get()
                    ->each(function($audioClip){
                        $audioClip->deleteRemoteResource();
                        $audioClip->delete();
                    });

        //  Bank or release phone number then delete
        PhoneNumber::where('company_id', $this->id)
                    ->get()
                    ->each(function($phoneNumber){
                        $phoneNumber->bankOrRelease();
                        $phoneNumber->delete();
                    });

        //
        //  Then everything else ...
        //
        Call::where('company_id', $this->id)->delete();
        
        UserCompany::where('company_id', $this->id)->delete();

        PhoneNumberPool::where('company_id', $this->id)->delete();

        PhoneNumberConfig::where('company_id', $this->id)->delete();

        BlockedCall::whereIn('blocked_phone_number_id', function($q){
                        $q->select('id')
                            ->from('blocked_phone_numbers')
                            ->where('blocked_phone_numbers.company_id', $this->id);
                    })->delete();

        BlockedPhoneNumber::where('company_id', $this->id)->delete();

        ReportAutomation::whereIn('report_id', function($q){
                              $q->select('id')
                                ->from('reports')
                                ->where('reports.company_id', $this->id);
                        })->delete();

        Report::where('company_id', $this->id)->delete();
    }
}
