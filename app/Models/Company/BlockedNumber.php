<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class BlockedNumber extends Model
{
    protected $fillable = [
        'company_id',
        'country_code',
        'number'
    ];

    public function company()
    {
        $this->belongsTo('\App\Models\Company');
    }
}
