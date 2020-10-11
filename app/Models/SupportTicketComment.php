<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketComment extends Model
{
    protected $fillable = [
        'account_id',
        'support_ticket_id',
        'comment',
        'created_by_user_id',
        'created_by_agent_id'
    ];

    protected $appends = [
        'kind',
        'link'
    ];

    public function getLinkAttribute()
    {
        return null;
    }

    public function getKindAttribute()
    {
        return 'SupportTicketComment';
    }
}
