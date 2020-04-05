<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use \App\Models\User;
use \App\Mail\Alert as AlertMail;

class Alert extends Model
{
    const TYPE_PRIMARY_METHOD_INVALID = 'PRIMARY_METHOD_FAILED';
    const TYPE_AUTO_RELOAD_FAILED     = 'AUTO_RELOAD_FAILED';
    const TYPE_BALANCE_LOW            = 'BALANCE_LOW';
    const TYPE_NOTIFICATION           = 'Notification';

    const CATEGORY_ERROR              = 'Error';
    const CATEGORY_WARNING            = 'Warning';
    

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'url'
    ];

    protected $appends = [
        'link',
        'kind'
    ];

    /**
     * Appends
     * 
     */
    public function getLinkAttribute()
    {
        return route('read-alert');
    }

    public function getKindAttribute()
    {
        return 'Alert';
    }

    static public function send(User $user, $fields)
    {
        $fields['user_id'] = $user->id;

        $alert = self::create($fields);

        if( ! $user->isOnline() )
            $user->email(new AlertMail($user, $alert));
    }
}
