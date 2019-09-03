<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class WebhookCall extends Model
{
    protected $fillable = [
        'company_id',
        'webhook_action_id',
        'resource_id',
        'method',
        'url',
        'status_code',
        'error'
    ];
}
