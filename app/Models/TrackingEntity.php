<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingEntity extends Model
{
    protected $table = 'tracking_entities';
    
    protected $fillable = [
        'id',
        'uuid',
        'account_id',
        'company_id',
        'fingerprint'
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u'; 
}
