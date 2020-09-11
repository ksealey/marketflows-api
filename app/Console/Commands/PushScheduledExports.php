<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \App\Models\Company\ScheduledExport;
use \App\Jobs\ExecuteScheduledExportJob;
use DB;

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
        ScheduledExport::where(DB::raw("DATE_FORMAT(CONVERT_TZ(NOW(), 'UTC', timezone), '%w|%k')"), DB::raw("CONCAT(day_of_week, '|', hour_of_day)"))
                        ->where(function($query){
                            $query->whereNull('last_export_at')
                                  ->orWhere(DB::raw("DATE_FORMAT(last_export_at, '%Y-%m-%d')"), "<", DB::raw("DATE_FORMAT(NOW(), '%Y-%m-%d')"));
                        })
                        ->get()
                        ->each(function($export){
                            ExecuteScheduledExportJob::dispatch($export);
                        });

        return 0;
    }
}
