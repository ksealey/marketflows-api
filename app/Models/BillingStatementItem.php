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

    public $timestamps = false;
}
