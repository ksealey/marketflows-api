<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'account_id',
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

    protected $appends = [
        'kind',
        'link'
    ];

    public function getKindAttribute()
    {
        return 'Webhook';
    }

    public function getLinkAttribute()
    {
        return route('read-webhook', [
            'company' => $this->company_id,
            'webhook' => $this->id
        ]);
    }

    const ACTION_CALL_START = 'call_start';
    const ACTION_CALL_END   = 'call_end';
    const ACTION_LIMIT = 3;

    static public function actions()
    {
        return [
            self::ACTION_CALL_START,
            self::ACTION_CALL_END
        ];
    }
}
