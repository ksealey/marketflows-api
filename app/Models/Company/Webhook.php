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
        'params',
        'enabled_at'
    ];

    const ACTION_CALL_START   = 'call.start';
    const ACTION_CALL_UPDATED = 'call.updated';
    const ACTION_CALL_END     = 'call.end';

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
