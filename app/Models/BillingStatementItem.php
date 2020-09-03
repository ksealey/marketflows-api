<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingStatementItem extends Model
{
    use SoftDeletes;

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'price_formatted',
        'total_formatted',
    ];

    public function getPriceFormattedAttribute()
    {
        return number_format($this->price, 2);
    }

    public function getTotalFormattedAttribute()
    {
        return number_format($this->total, 2);
    }
}
