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
        'created_by',
        'name',
        'webhook_actions'
    ];

    protected $hidden = [
        'account_id',
        'created_by',
        'deleted_at'
    ];
}
