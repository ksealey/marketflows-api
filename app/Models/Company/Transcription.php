<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class Transcription extends Model
{
    public $timestamps = false;

    public function getKindAttribute()
    {
        return 'Transcription';
    }
}
