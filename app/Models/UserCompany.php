<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserCompany extends Model
{   
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'company_id'
    ];
}
