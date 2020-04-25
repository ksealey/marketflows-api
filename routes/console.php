<?php

use Illuminate\Foundation\Inspiring;
use \App\Models\Company\Call;
use \App\Models\Company\ReportAutomation;
use  \App\Jobs\ExecuteReportAutomation;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');


Artisan::command('clear:password-resets', function () {
    \App\Models\Auth\PasswordReset::where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
})->describe('Clear expired password resets');


Artisan::command('reports:dispatch-automations', function(){
    //  
    //  Pull pending
    //
    $now         = now();
    $dayOfWeek   = $now->format('N');
    $time        = $now->format('H:i:s');
    $today       = $now->format('Y-m-d');
    $automations = ReportAutomation::where('day_of_week', $dayOfWeek)
                                   ->where('time', '<=', $time)
                                   ->where(function($query) use($today){
                                        $query->whereNull('last_ran_at')
                                              ->orWhere('last_ran_at', '!=', $today);
                                   })
                                   ->whereNull('locked_since')
                                   ->get();

    //
    //  Place lock on current records
    //
    $ids = array_column($automations->toArray(), 'id');
    if( count($ids) ){
        ReportAutomation::whereIn('id', $ids)->update([
            'locked_since' => now()
        ]);
    }

    //
    //  Dispatch jobs
    //
    foreach($automations as $automation){
        ExecuteReportAutomation::dispatch($automation);
    }

    //
    //  Clear any old locks for a retry
    //
    $fifteenMinutesAgo = now()->subMinutes(15);
    ReportAutomation::where('locked_since', '<=', $fifteenMinutesAgo)->update([
        'locked_since' => null
    ]);
});


Artisan::command('fill-calls', function(){
    $sources = [
        'Facebook', 'Twitter', 'Yahoo', 'WebMD', 'Kellogs', 'Google'
    ];
    for( $i = 0; $i < 1000; $i++){
        Call::create([
            'account_id'                => 1,
            'company_id'                => 1,
            'phone_number_id'           => 1,
            'type'                      => 'Toll-Free',
            'category'                  => 'OFFLINE',
            'sub_category'              => 'EMAIL',

            'phone_number_pool_id'      => null,
            'session_id'                => null,

            'caller_id_enabled'         => true,
            'recording_enabled'         => true,
            'forwarded_to'              => '8135573005',
            
            'external_id'               => str_random(40),
            'direction'                 => 'Inbound',
            'status'                    => 'Completed',
            
            'caller_first_name'         => 'Jamie',
            'caller_last_name'          => 'Smith',
            'caller_country_code'       => 1,
            'caller_number'             => '813' . mt_rand(1111111,9999999),
            'caller_city'               => 'New York',
            'caller_state'              => 'New York',
            'caller_zip'                => '409483',
            'caller_country'            => 'US',
            
        
            'source'                    => $sources[mt_rand(0, count($sources)-1)],
            'medium'                    => 'Medium',
            'content'                   => 'Content',
            'campaign'                  => 'Campaign',
            'created_at'                => now()->subtract('1 day')->format('Y-m-d H:i:s.u'),
            'updated_at'                => now()->subtract('1 day')->format('Y-m-d H:i:s.u')
        ]);
    }
});
