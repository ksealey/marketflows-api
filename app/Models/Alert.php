<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\User;
use \App\Mail\Alert as AlertMail;

class Alert extends Model
{
    use SoftDeletes;

    const TYPE_DEFAULT    = 'default';
    const TYPE_ERROR      = 'error';
    const TYPE_WARNING    = 'warning';
    const TYPE_SUCCESS    = 'success';
    const TYPE_FILE       = 'file';

    const ICON_FILE = 'file';
    
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'icon',
        'url',
        'url_label',
        'hidden_after'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    protected $hidden = [
        'hidden_after',
        'deleted_at'
    ];

    /**
     * Appends
     * 
     */
    public function getLinkAttribute()
    {
        return route('delete-alert', [
            'alert' => $this->id
        ]);
    }

    public function getKindAttribute()
    {
        return 'Alert';
    }
}
