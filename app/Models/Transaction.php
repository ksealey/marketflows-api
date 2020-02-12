<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions'; 

    protected $fillable = [
        'account_id',
        'company_id',
        'created_by',
        'identifier',
        'external_id',
        'price',
        'object',
        'label',
        'created_at',
        'updated_at'
    ];
}
