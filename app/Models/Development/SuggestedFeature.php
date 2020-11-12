<?php

namespace App\Models\Development;

use Illuminate\Database\Eloquent\Model;

class SuggestedFeature extends Model
{
    protected $fillable = [
        'url',
        'details',
        'created_by'
    ];
}
