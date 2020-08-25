<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $hidden = [
        'deleted_at',
        'deleted_by'
    ];

    protected $appends = [
        'kind',
        'link'
    ];

    public function getKindAttribute()
    {
        return 'Service';
    }

    public function getLinkAttribute()
    {
        return null;
    }


}
