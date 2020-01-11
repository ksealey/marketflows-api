<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\Campaign;
use App\Models\Plugin;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies'; 

    protected $fillable = [
        'account_id',
        'created_by',
        'name',
        'industry',
        'country',
        'timezone',
    ];

    protected $hidden = [
        'account_id',
        'created_by',
        'deleted_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    /**
     * Check if this company is in use
     * 
     * @return bool
     */
    public function isInUse()
    {
        $activeCampaignCount = Campaign::where('company_id', $this->id)
                                       ->whereNotNull('activated_at')
                                       ->count();
        
        return $activeCampaignCount ? true : false;
    }

    public function plugins()
    {
        return Plugin::whereIn('id', function($query){
            $query->select('plugin_id')
                  ->from('company_plugins')
                  ->where('company_id', $this->id);
        })->get();
    }

    public function getLinkAttribute()
    {
        return route('read-company', [
            'companyId' => $this->id
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
}
