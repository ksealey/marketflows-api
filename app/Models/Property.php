<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use SoftDeletes;

    protected $table = 'properties';

    protected $hidden = [
        'company_id',
        'created_by',
        'deleted_at'
    ];

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'domain',
        'key'
    ];

    static public function domain($url)
    {
        preg_match('/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]/', $url, $matches);

        return !empty($matches[0]) ? $matches[0] : null;
    }
}
