<?php

namespace App\Models\Development;

use Illuminate\Database\Eloquent\Model;

class BugReport extends Model
{
    protected $fillable = [
        'url',
        'details',
        'created_by'
    ];
}
