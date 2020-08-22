<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketAttachment extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'support_ticket_comment_id',
        'file_name',
        'file_size',
        'file_mime_type',
        'path',
        'created_by_user_id',
        'created_by_agent_id'
    ];
}
