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
            'plugins.billing_label',
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

    public function install(Request $request, Company $company)
    {
        $rules = [
            'plugin_key' => 'bail|required|exists:plugins,key',
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $companyPlugin = CompanyPlugin::where('company_id', $company->id)
                                        ->where('plugin_key', $request->plugin_key)
                                        ->first();
        if( $companyPlugin ){
            return response([
                'error' => 'Plugin already installed'
            ], 400);
        }

        $companyPlugin = CompanyPlugin::create([
            'company_id' => $company->id,
            'plugin_key' => $request->plugin_key,
            'settings'   => json_encode([]),
            'enabled_at' => null
        ]);

        return response($companyPlugin->withPluginDetails(), 201);
    }

    public function read(Request $request, Company $company, CompanyPlugin $companyPlugin)
    {
        return response($companyPlugin->withPluginDetails());
    }

    public function update(Request $request, Company $company, CompanyPlugin $companyPlugin)
    {
        $rules = [
            'settings' => ['bail', 'json', new PluginSettingsRule($companyPlugin->plugin_key)],
            'enabled'  => 'bail|boolean'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        if( $request->filled('settings') ){
            $companyPlugin->settings = $request->settings;
        }

        if( $request->filled('enabled') ){
            $companyPlugin->enabled_at = $request->enabled ? now() : null;
        }

        $companyPlugin->save();

        return response($companyPlugin->withPluginDetails());
    }


    public function uninstall(Request $request, Company $company, CompanyPlugin $companyPlugin)
    {
        $companyPlugin->delete();

        return response([
            'message' => 'Uninstalled'
        ]);
    }
}
