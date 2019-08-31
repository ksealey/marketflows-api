<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class CampaignSpend extends Model
{
    protected $fillable = [
        'campaign_id',
        'from_date',
        'to_date',
        'total'
    ];
}
