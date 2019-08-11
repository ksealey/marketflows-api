<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampaignSpend extends Model
{
    use SoftDeletes;

    protected $hidden = [
        'company_id',
        'deleted_at'
    ];
}
