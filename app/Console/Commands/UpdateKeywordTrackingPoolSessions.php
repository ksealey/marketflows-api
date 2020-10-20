<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \App\Models\Company\KeywordTrackingPoolSession;

class UpdateKeywordTrackingPoolSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keyword-tracking-pools:update-sessions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update keyword tracking pool sessions';

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
        //  End sessions that will be dead on the browser that have not been active in the last 3 minutes
        KeywordTrackingPoolSession::where('last_activity_at', '<=', now()->subMinutes(5))
                                  ->where('end_after', '<=', now())
                                  ->whereNull('ended_at')
                                  ->update([
                                        'active'    => 0,
                                        'ended_at'  => now()
                                    ]);

        //  Deactivate sessions that have had no activity for 3 minutes
        $sessions = KeywordTrackingPoolSession::where('last_activity_at', '<=', now()->subMinutes(5))
                                                    ->where('active', 1)
                                                    ->whereNull('ended_at')
                                                    ->update(['active' => 0]);
        return 0;
    }
}
