<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingSessionEvent extends Model
{
    public $timestamps = false; 

    const SESSION_START = 'SessionStart';
    const CLICK_TO_CALL = 'ClickToCall';
    const PAGE_VIEW     = 'PageView';

    protected $fillable = [
        'tracking_session_id',
        'event_type',
        'content',
        'created_at'
    ];

    protected $dates = [
        'created_at'
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function tracking_session()
    {
        return $this->belongsTo('\App\Models\TrackingSession');
    }
}
