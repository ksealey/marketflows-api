<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportTicketAttachment extends Model
{
    protected $fillable = [
        'account_id',
        'support_ticket_id',
        'support_ticket_comment_id',
        'file_name',
        'file_size',
        'file_mime_type',
        'path',
        'created_by_user_id',
        'created_by_agent_id'
    ];

    protected $appends = [
        'kind',
        'link',
        'url'
    ];

    public function getLinkAttribute()
    {
        return null;
    }

    public function getKindAttribute()
    {
        return 'SupportTicketAttachment';
    }

    public function getUrlAttribute()
    {
        return trim(config('app.cdn_url'), '/') . '/' . trim($this->path, '/');
    }
}
