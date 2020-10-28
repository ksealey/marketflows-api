<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    protected $appends = [
        'kind',
        'link'
    ];

    protected $hidden = [
        'rules'
    ];

    public function getKindAttribute()
    {
        return 'Plugin';
    }

    public function getLinkAttribute()
    {
        return '';
    }

    public function getRulesAttribute($rules)
    {
        return $rules ? (json_decode($rules) ?: []) : []; 
    }
}
