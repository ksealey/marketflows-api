<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    const TYPE_PURCHASE = 'PURCHASE';
    const TYPE_CALL     = 'CALL';
    const TYPE_SMS      = 'SMS';

    public $timestamps = false;

    protected $table = 'transactions'; 

    protected $fillable = [
        'account_id',
        'company_id',
        'user_id',
        'amount',
        'type',
        'item',
        'table' ,
        'record_id',
        'label',
        'created_at'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

     /**
     * Appends
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-transaction', [
            $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'Transaction';
    }
}
