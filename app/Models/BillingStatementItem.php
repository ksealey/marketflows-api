<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingStatementItem extends Model
{
    protected $fillable = [
        'billing_statement_id',
        'label',
        'description',
        'total'
    ];

    public $appends = [
        'total_formatted'
    ];

    public $timestamps = false;

    public function getTotalFormattedAttribute()
    {
        return number_format($this->total, 2);
    }
}
