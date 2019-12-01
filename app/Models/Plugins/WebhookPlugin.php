<?php
namespace App\Models\Plugins;

use App\Models\Company;
use App\Models\Plugin;

class WebhookPlugin extends Plugin
{
    /**
     * Execute the plugin
     * 
     */
    public function execute(Company $company, string $hook, array $data = []) : boolean
    {   
        //
        //  Execute the webhooks
        //
        
        var_dump('EXIT'); exit;
        return true;
    }
}