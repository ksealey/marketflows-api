<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alert;

class AlertController extends Controller
{
     /**
     * List all alerts
     * 
     * @param Request $request
     * 
     * @return Response
     */
    public function list(Request $request)
    {
        $query = Alert::where('user_id', $request->user()->id)
                      ->where(function($query){
                            $query->whereNull('hidden_after')
                                  ->orWhere('hidden_after', '>', now());
                      });
                      
        return parent::results(
            $request,
            $query,
            [],
            Alert::accessibleFields(),
            'alerts.created_at'
        );
    }

    public function delete(Request $request, Alert $alert)
    {
        $alert->delete();

        return response([
            'message' => 'Deleted.'
        ]);
    }
}
