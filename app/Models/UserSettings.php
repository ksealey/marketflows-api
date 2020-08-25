<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSettings extends Model
{
	protected $table = 'user_settings';
    
	protected $fillable = [
		'user_id',
		'email_notifications_enabled',
		'sms_notifications_enabled',
	];   

	public $appends = [
        'kind',
        'link'
    ];

	public function getLinkAttribute()
    {
        return null;
    }

    public function getKindAttribute()
    {
        return 'UserSettings';
    }
}
