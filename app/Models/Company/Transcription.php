<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class Transcription extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'call_id',
        'text'
    ];

    protected $appends = [
        'url',
        'kind'
    ];

    public function getKindAttribute()
    {
        return 'Transcription';
    }

    public function getUrlAttribute()
    {
        return null;
    }
}
