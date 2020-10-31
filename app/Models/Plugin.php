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
        'google-analytics' => GoogleAnalyticsPlugin::class,
        'webhooks'         => WebhooksPlugin::class
    ];

    protected $appends = [
        'kind',
        'link',
        'image_url'
    ];

    public function getKindAttribute()
    {
        return 'Plugin';
    }

    public function getLinkAttribute()
    {
        return '';
    }

    public function getImageUrlAttribute()
    {
        return  config('app.cdn_url') . $this->image_path;
    }

    public static function generate($pluginKey)
    {
        return new self::$plugins[$pluginKey];
    }
}
