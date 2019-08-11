<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AudioClip extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'path',
        'mime_type'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    public function canBeDeleted()
    {
        return true;
    }
}
