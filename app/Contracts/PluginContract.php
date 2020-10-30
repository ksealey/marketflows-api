<?php
namespace App\Contracts;

use Illuminate\Http\Request;
use App\Models\Company\CompanyPlugin;

interface PluginContract
{
    public function onRules(Request $request);
    public function onHook(object $hook, CompanyPlugin $companyPlugin);
}