<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class CampaignDomain extends Model
{
    protected $fillable = [
        'campaign_id',
        'domain'
    ];
}
