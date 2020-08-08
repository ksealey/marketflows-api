<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = [
        'company_id',
        'action',
        'method',
        'url',
        'enabled_at'
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

    public function getParamsAttribute($params)
    {
        return json_decode($params);
    }
}
