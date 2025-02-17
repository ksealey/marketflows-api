<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use \App\Models\User;

class Alert extends Model
{
    use SoftDeletes;

    const TYPE_DEFAULT    = 'info';
    const TYPE_DANGER     = 'danger';
    const TYPE_WARNING    = 'warning';
    const TYPE_SUCCESS    = 'success';
    const TYPE_FILE       = 'file';

    const CATEGORY_GENERAL = 'GENERAL';
    const CATEGORY_PAYMENT = 'PAYMENT';
    
    protected $fillable = [
        'account_id',
        'user_id',
        'category',
        'type',
        'title',
        'message',
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

    public static function accessibleFields()
    {
        return [
            'alerts.id',
            'alerts.category',
            'alerts.type',
            'alerts.title',
            'alerts.message',
            'alerts.created_at'
        ];
    }

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
