<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies'; 

    protected $fillable = [
        'name',
    ];

    protected $hidden = [
        'stripe_id',
        'disabled_at',
        'deleted_at'
    ];
}
