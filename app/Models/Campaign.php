<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    protected $hidden = [
        'company_id',
        'suspended_at',
        'deleted_at',
    ];

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'type',
        'starts_at',
        'ends_at'
    ];

    const TYPE_WEB   = 'WEB';
    const TYPE_TV    = 'TV';
    const TYPE_RADIO = 'RADIO';
    const TYPE_PRINT = 'PRINT';

    static public function types()
    {
        return [
            self::TYPE_WEB,
            self::TYPE_TV,
            self::TYPE_RADIO,
            self::TYPE_PRINT
        ];
    }
}
