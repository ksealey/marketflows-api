<?php

namespace App\Models\Events;

use Illuminate\Database\Eloquent\Model;

class SessionEvent extends EventModel
{
    public $timestamps = false; 

    protected $fillable = [
        'id',
        'session_id',
        'event_type',
        'is_public',
        'content',
        'created_at'
    ];
}
