<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{

    const URGENCY_LOW        = 'LOW';
    const URGENCY_MEDIUM     = 'MEDIUM';
    const URGENCY_HIGH       = 'HIGH'; 

    const STATUS_UNASSIGNED         = 'UNASSIGNED';
    const STATUS_IN_PROGRESS        = 'IN_PROGRESS';
    const STATUS_PENDING_RESPONSE   = 'PENDING_RESPONSE';
    const STATUS_CLOSED             = 'CLOSED';

    protected $fillable = [
        'account_id',
        'urgency',
        'subject',
        'description',
        'created_by_user_id',
        'created_by_agent_id',
        'agent_id',
        'status',
        'closed_at',
        'closed_by'
    ];

    protected $hidden = [
        'created_by_user_id',
        'created_by_agent_id',
        'deleted_at'
    ];

    protected $appends = [
        'kind',
        'link'
    ];

    public function getLinkAttribute()
    {
        return route('read-support-ticket', [
            'supportTicket' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'SupportTicket';
    }

    public function comments()
    {
        return $this->hasMany('\App\Models\SupportTicketComment')
                    ->orderBy('created_at', 'asc');
    }

    public function attachments()
    {
        return $this->hasMany('\App\Models\SupportTicketAttachment');
    }

    public function agent()
    {
        return $this->belongsTo('\App\Models\Agent');
    }

    public static function urgencies()
    {
        return [
            SupportTicket::URGENCY_LOW,
            SupportTicket::URGENCY_MEDIUM,
            SupportTicket::URGENCY_HIGH
        ];
    }
}
