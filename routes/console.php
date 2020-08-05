<?php

use Illuminate\Foundation\Inspiring;
use \App\Models\Account;
use \App\Models\Billing;
use \App\Models\Company\Call;
use \App\Models\Company\CallRecording;
use \App\Models\Company\ReportAutomation;
use \App\Jobs\ExecuteReportAutomation;
use \App\Jobs\CreateBillingStatementJob;
use \App\Jobs\BillAccountJob;
use \App\Jobs\AccountSuspensionWarningJob;
use \App\Jobs\ReleaseSuspendedAccountNumbersJob;

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

Artisan::command('accounts:suspension-warnings', function(){
    $accounts = Account::where('suspension_warning_at', '<=', now())->get();

    foreach( $accounts as $account ){
        AccountSuspensionWarningJob::dispatch($account);
    }
});

Artisan::command('accounts:release-suspended-numbers', function(){
    $sevenDaysAgo = now()->subDays(8);
    $accounts     = Account::where('suspension_warning_at', '<=', $sevenDaysAgo)
                           ->get();

    foreach( $accounts as $account ){
        ReleaseSuspendedAccountNumbersJob::dispatch($account);
    }
});



Artisan::command('fill-calls', function(){
    $sources = [
        'Facebook', 'Twitter', 'Yahoo', 'WebMD', 'Kellogs', 'Google'
    ];
    
    for( $i = 0; $i < 1000; $i++){
        $recordingEnabled = mt_rand(0,1);
        $callerIdEnabled  = mt_rand(0,1);
        $created          = mt_rand(0,1) ? now() : now()->subtract('-' . mt_rand(1,10) .' days');
        $type   = mt_rand(0,1) ? 'Toll-Free' : 'Local';
        $call = Call::create([
            'account_id'                => 1,
            'company_id'                => 1,
            'phone_number_id'           => 1,
            'type'                      => $type,
            'category'                  => 'OFFLINE',
            'sub_category'              => 'EMAIL',

            'recording_enabled'         => $recordingEnabled,
            'forwarded_to'              => '8135573005',
            
            'external_id'               => str_random(40),
            'direction'                 => 'Inbound',
            'status'                    => 'Completed',
            
            'duration'                  => mt_rand(0, 100),
            'source'                    => $sources[mt_rand(0, count($sources)-1)],
            'medium'                    => 'Medium',
            'content'                   => 'Content',
            'campaign'                  => 'Campaign',
            'created_at'                => $created ,
            'updated_at'                => $created 
        ]);

        if( $recordingEnabled ){
            CallRecording::create([
                'call_id' => $call->id,
                'external_id' => str_random(32),
                'duration' => mt_rand(0, 100),
                'file_size' => mt_rand(1024, 1024 * 1024 * 20),
                'path' => '/path/to/file/' . str_random(20) . '.mp3',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
});
