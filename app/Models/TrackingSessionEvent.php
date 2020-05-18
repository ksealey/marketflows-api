<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSessionEvent extends Model
{
    public $timestamps = false; 

    const SESSION_START = 'SessionStart';
    const CLICK_TO_CALL = 'ClickToCall';

    protected $fillable = [
        'tracking_session_id',
        'event_type',
        'content',
        'created_at'
    ];
}
