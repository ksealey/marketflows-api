<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Development\SuggestedFeature;
use App\Models\Development\BugReport;
use App\Mail\Development\SuggestedFeature as SuggestedFeatureMail;
use App\Mail\Development\BugReport as BugReportMail;
use Mail;

class DevelopmentController extends Controller
{
    /**
     * Suggest a feature
     * 
     * @param Request
     * 
     * @return Response
     * 
     */
    public function suggestFeature(Request $request)
    {
        $rules = [
            'url'     => 'required|url|max:255',
            'details' => 'required|max:500'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();

        $suggestedFeature = SuggestedFeature::create([
            'url'        => $request->url,
            'details'    => $request->details,
            'created_by' => $user->id
        ]);


        Mail::to($user)->bcc(config('mail.to.development.address'))->queue(
            new SuggestedFeatureMail($user, $suggestedFeature)
        );

        return response($suggestedFeature, 201);
    }

    /**
     * Report a bug
     * 
     * @param Request
     * 
     * @return Response
     */
    public function reportBug(Request $request)
    {
        $rules = [
            'url'     => 'required|url|max:255',
            'details' => 'required|max:500'
        ];

        $validator = validator($request->input(), $rules);
        if( $validator->fails() ){
            return response([
                'error' => $validator->errors()->first()
            ], 400);
        }

        $user = $request->user();

        $bugReport = BugReport::create([
            'url'        => $request->url,
            'details'    => $request->details,
            'created_by' => $user->id
        ]);
    
        Mail::to($user)->bcc(config('mail.to.development.address'))->queue(
            new BugReportMail($user, $bugReport)
        );

        return response($bugReport, 201);
    }
}
