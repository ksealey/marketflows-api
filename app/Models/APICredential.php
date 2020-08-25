<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class APICredential extends Model
{
    protected $table = 'api_credentials';

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'secret'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'last_used_at'
    ];

    protected $hidden = [
        'secret'
    ];

    protected $appends = [
        'kind',
        'link'
    ];

    public function getKindAttribute()
    {
        return 'APICredential';
    }

    public function getLinkAttribute()
    {
        return null;
    }

}
