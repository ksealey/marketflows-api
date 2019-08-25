<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountCompany extends Model
{
    protected $fillable = [
        'account_id',
        'company_id'
    ];
}
