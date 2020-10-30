<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Plugins\GoogleAnalyticsPlugin;
use App\Plugins\WebhooksPlugin;

class Plugin extends Model
{
    const EVENT_CALL_START   = 'CALL_START';
    const EVENT_CALL_END     = 'CALL_END';

    public static $plugins = [
        'google_analytics' => GoogleAnalyticsPlugin::class,
        'webhooks'         => WebhooksPlugin::class
    ];

    protected $appends = [
        'kind',
        'link'
    ];

    protected $hidden = [
        'rules'
    ];

    public function getKindAttribute()
    {
        return 'Plugin';
    }

    public function getLinkAttribute()
    {
        return '';
    }

    public function getRulesAttribute($rules)
    {
        return $rules ? (json_decode($rules) ?: []) : []; 
    }

    public static function generate($pluginKey)
    {
        return new self::$plugins[$pluginKey];
    }
}
