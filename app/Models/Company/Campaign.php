<?php

namespace App\Models\Company;

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
        'uuid',
        'company_id',
        'created_by',
        'name',
        'type',
        'phone_number_pool_id',
        'activated_at',
        'suspended_at',
        'number_swap_rules'
    ];

    const TYPE_WEB   = 'WEB';
    const TYPE_TV    = 'TV';
    const TYPE_RADIO = 'RADIO';
    const TYPE_PRINT = 'PRINT';

    public function company()
    {
        return $this->belongsTo('\App\Models\Company');
    }
    
    static public function types()
    {
        return [
            self::TYPE_WEB,
            self::TYPE_TV,
            self::TYPE_RADIO,
            self::TYPE_PRINT
        ];
    }

    public function isActive()
    {
        return $this->activated_at 
            && ! $this->suspended_at;
    }
}
