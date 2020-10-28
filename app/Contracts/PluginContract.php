<?php
namespace App\Contracts;

interface PluginContract
{
    public function onHook($hook);
}