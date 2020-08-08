<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'company_id',
        'action',
        'method',
        'url',
        'enabled_at',
        'created_by'
    ];

    protected $hidden = [
        'deleted_at',
        'deleted_by'
    ];

    const ACTION_CALL_START   = 'call_start';
    const ACTION_CALL_UPDATED = 'call_update';
    const ACTION_CALL_END     = 'call_end';

    const ACTION_LIMIT = 3;

    static public function actions()
    {
        return [
            self::ACTION_CALL_START,
            self::ACTION_CALL_UPDATED,
            self::ACTION_CALL_END
        ];
    }
}
