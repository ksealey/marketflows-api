<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies'; 

    protected $fillable = [
        'account_id',
        'name',
    ];

    protected $hidden = [
        'account_id',
        'deleted_at'
    ];
}
