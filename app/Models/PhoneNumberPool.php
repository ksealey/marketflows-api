<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhoneNumberPool extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'created_by',
        'name', 
        'source', 
        'forward_to_country_code',
        'forward_to_number',
        'audio_clip_id'
    ];

    protected $hidden = [
        'company_id',
        'created_by',
        'deleted_at',
        'audio_clip_id'
    ];

    public function isInUse()
    {
        return false;
    }
}
