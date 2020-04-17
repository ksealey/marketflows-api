<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    protected $table = 'purchases';

    protected $fillable = [
        'account_id',
        'company_id',
        'user_id',
        'item',
        'total',
        'description'
    ];

    public $appends = [
        'kind',
        // Add url
        // ...
    ];

    public function getKindAttribute()
    {
        return 'Purchase';
    }


}
