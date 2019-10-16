<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Company\Campaign;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies'; 

    protected $fillable = [
        'account_id',
        'created_by',
        'name',
        'webhook_actions'
    ];

    protected $hidden = [
        'account_id',
        'created_by',
        'deleted_at'
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
}
