<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    public $hidden = [
        'class_prefix',
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}
