<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserEvent extends Model
{
    protected $fillable = [
        'user_id',
        'event',
        'resource_id',
        'changes'
    ];
}
