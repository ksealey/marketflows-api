<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketComment extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'comment',
        'created_by_user_id',
        'created_by_agent_id'
    ];
}
