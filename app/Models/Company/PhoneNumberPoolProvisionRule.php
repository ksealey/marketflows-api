<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class PhoneNumberPoolProvisionRule extends Model
{
    protected $fillable = [
        'phone_number_pool_id',
        'country',
        'area_code',
        'priority'
    ];
}
