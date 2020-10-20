<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auth\EmailVerification;

class ClearExpiredEmailVerifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear-expired-email-verifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear expired email verifications';

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
        EmailVerification::where('expires_at', '<', now())
                         ->delete();
        return 0;
    }
}
