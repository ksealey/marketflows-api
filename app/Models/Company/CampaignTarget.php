<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class CampaignTarget extends Model
{
    protected $fillable = [
        'campaign_id',
        'rules'
    ];
}
