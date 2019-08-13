<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignPhoneNumberPool extends Model
{
    protected $hidden = [
        'company_id',
        'deleted_at'
    ];

    protected $fillable = [
        'campaign_id',
        'phone_number_pool_id'
    ];
}
