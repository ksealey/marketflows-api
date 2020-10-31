<?php
namespace App\Contracts;

use Illuminate\Http\Request;
use App\Models\Company\CompanyPlugin;

interface PluginContract
{
    public function onValidateSettings(object $settings);
    public function onHook(object $hook, CompanyPlugin $companyPlugin);
    public function onDefaultSettings();
}