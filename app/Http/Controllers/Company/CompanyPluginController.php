<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plugin;
use App\Models\Company;
use App\Models\Company\CompanyPlugin;
use App\Rules\PluginSettingsRule;

class CompanyPluginController extends Controller
{
    public function list(Request $request, Company $company)
    {
        $rules = [
            'installed_search' => 'bail|string',
            'available_search' => 'bail|string'
        ];
    
        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' =>  $validatr->errors()->first()
            ], 400);
        }

        $query = CompanyPlugin::select([
            'company_plugins.*',
            'plugins.name',
            'plugins.details',
            'plugins.image_path',
            'plugins.price'
        ])->where('company_id', $company->id);
        if( $request->installed_search ){
            $query->where(function($query) use($request){
                $query->where('plugins.name', 'like', '%' . $request->installed_search . '%')
                      ->orWhere('plugins.details', 'like', '%' . $request->installed_search . '%');
            });
        }
        $query->leftJoin('plugins', 'plugins.key', 'company_plugins.plugin_key');
        $installed = $query->get();

        $query = Plugin::whereNotIn('key', function($query) use($company){
            $query->select('plugin_key')
                  ->from('company_plugins')
                  ->where('company_id', $company->id);
        });
        if( $request->available_search ){
            $query->where(function($query) use($request){
                $query->where('plugins.name', 'like', '%' . $request->available_search . '%')
                      ->orWhere('plugins.details', 'like', '%' . $request->available_search . '%');
            });
        }
        $available = $query->get();

        return response([
            'results' => [
                'installed' => [
                    'total'   => count($installed),
                    'results' => $installed
                ],
                'available' => [
                    'total'   => count($available),
                    'results' => $available
                ]
            ]
        ]);
    }

    public function install(Request $request, Company $company, string $pluginKey)
    {
        $plugin = Plugin::where('key', $pluginKey)->first();
        if( ! $plugin ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        $companyPlugin = CompanyPlugin::where('company_id', $company->id)
                                        ->where('plugin_key', $pluginKey)
                                        ->first();
        if( $companyPlugin ){
            return response([
                'error' => 'Plugin already installed'
            ], 400);
        }

        $companyPlugin = CompanyPlugin::create([
            'company_id' => $company->id,
            'plugin_key' => $pluginKey,
            'settings'   => null,
            'enabled_at' => null
        ]);

        return response($companyPlugin->withPluginDetails(), 201);
    }

    public function read(Request $request, Company $company, string $pluginKey)
    {
        //  Make sure it's installed for this company
        $companyPlugin = CompanyPlugin::where('company_id', $company->id)
                                      ->where('plugin_key', $pluginKey)
                                      ->first();

        if( ! $companyPlugin ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        return response($companyPlugin->withPluginDetails());
    }

    public function update(Request $request, Company $company, string $pluginKey)
    {
        //  Make sure it's installed for this company
        $companyPlugin = CompanyPlugin::where('company_id', $company->id)
                                      ->where('plugin_key', $pluginKey)
                                      ->first();

        if( ! $companyPlugin ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        $rules = [
            'settings' => ['bail', 'required', 'json'],
            'enabled'  => 'bail|boolean'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $plugin        = Plugin::generate($pluginKey);
        $settingsValid = $plugin->onValidateSettings((object)json_decode($request->settings));
        if( ! $settingsValid ){
            return response([
                'error' => 'Settings invalid'
            ], 400);
        }

        if( $request->filled('settings') ){
            $companyPlugin->settings = $request->settings;
        }

        if( $request->filled('enabled') ){
            $companyPlugin->enabled_at = $request->enabled ? ($companyPlugin->enabled_at ?: now()) : null;
        }

        $companyPlugin->save();

        return response($companyPlugin->withPluginDetails());
    }


    public function uninstall(Request $request, Company $company, string $pluginKey)
    {
        //  Make sure it's installed for this company
        $companyPlugin = CompanyPlugin::where('company_id', $company->id)
                                      ->where('plugin_key', $pluginKey)
                                      ->first();

        if( ! $companyPlugin ){
            return response([
                'error' => 'Not found'
            ], 404);
        }

        $companyPlugin->delete();

        return response([
            'message' => 'Uninstalled'
        ]);
    }
}
