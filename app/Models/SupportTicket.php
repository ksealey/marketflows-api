<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    const STATUS_UNASSIGNED  = 'UNASSIGNED';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_CLOSED      = 'CLOSED';

    protected $fillable = [
        'subject',
        'description',
        'created_by_user_id',
        'created_by_agent_id',
        'agent_id',
        'status'
    ];

    protected $hidden = [
        'created_by_user_id',
        'created_by_agent_id',
        'deleted_at'
    ];

    public function comments()
    {
        return $this->hasMany('\App\Models\SupportTicketComment');
    }

    public function agent()
    {
        return $this->belongsTo('\App\Models\Agent');
    }
}
