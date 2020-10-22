<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\PayStatementJob;
use App\Models\BillingStatement;

class PayStatements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pay-statements';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pay statements';

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
        $statements = BillingStatement::whereNotNull('next_payment_attempt_at')
                                      ->where('next_payment_attempt_at', '<=', now())
                                      ->whereNull('locked_at')
                                      ->get();
        
        if( ! count($statements) ) return;

        foreach( $statements as $statement ){
            PayStatementJob::dispatch($statement);
        }

        return 0;
    }
}
