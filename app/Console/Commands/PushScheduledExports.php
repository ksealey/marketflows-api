<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \App\Models\Company\ScheduledExport;
use \App\Jobs\ExecuteScheduledExportJob;

class PushScheduledExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push-scheduled-exports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Push scheduled exports';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //
        //  Lock so no other server can get them
        //
        $now = now();
        ScheduledExport::where('next_run_at', '<=', $now)
                        ->whereNull('locked_at')
                        ->limit(1000)
                        ->update([
                            'locked_at' => $now
                        ]);

        //
        //  Get list
        //
        $exports = ScheduledExport::where('locked_at', $now)
                                    ->get();

        foreach( $exports as $export ){
            ExecuteScheduledExportJob::dispatch($export);
        }

        return 0;
    }
}
