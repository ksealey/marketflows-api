<?php

namespace App\Plugins;

use Illuminate\Database\Eloquent\Model;
use App\Contracts\PluginContract;

class GoogleAnalytics extends Model implements Plugin
{
    public function onHook($hook)
    {
        //
        //  Do stuff here
        //  ...
        //
    }
}
