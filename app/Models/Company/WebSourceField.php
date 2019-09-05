<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class WebSourceField extends Model
{
    protected $fillable = [
        'company_id',
        'label',
        'url_parameter',
        'default_value',
        'direct_value'
    ];
}
