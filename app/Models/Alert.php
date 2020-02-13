<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    const TYPE_PRIMARY_METHOD_INVALID = 'PRIMARY_METHOD_FAILED';
    const TYPE_AUTO_RELOAD_FAILED     = 'AUTO_RELOAD_FAILED';
    const TYPE_BALANCE_LOW            = 'BALANCE_LOW';

    const CATEGORY_ERROR              = 'ERROR';
    const CATEGORY_WARNING            = 'WARNING';

    protected $fillable = [
        'user_id',
        'type',
        'message'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    /**
     * Appends
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-alert');
    }

    public function getKindAttribute()
    {
        return 'Alert';
    }
}
