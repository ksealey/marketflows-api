<?php

namespace App\Models\Company;

use Illuminate\Database\Eloquent\Model;

class CompanyPlugin extends Model
{
    protected $appends = [
        'kind',
        'link',
        'image_url'
    ];

    public $fillable = [
        'company_id',
        'plugin_key',
        'settings',
        'enabled_at'
    ];

    public function getSettingsAttribute($settings)
    {
        return $settings ? (json_decode($settings) ?: []) : []; 
    }

    public function getKindAttribute()
    {
        return 'CompanyPlugin';
    }

    public function getLinkAttribute()
    {
        return route('read-plugin', [
            'company'       => $this->company_id,
            'pluginKey'     => $this->plugin_key
        ]);
    }

    public function getImageUrlAttribute()
    {
        return  config('app.cdn_url') . $this->image_path;
    }

    public function withPluginDetails()
    {
        return CompanyPlugin::select([
            'company_plugins.*',
            'plugins.name',
            'plugins.details',
            'plugins.image_path',
            'plugins.price'
        ])->where('company_plugins.id', $this->id)
          ->leftJoin('plugins', 'plugins.key', 'company_plugins.plugin_key')
          ->first();
    }

}
