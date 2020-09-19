<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Billing;
use App\Jobs\BillAccountJob;

class PushBillAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push-bill-accounts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bill accounts';

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
     * @return mixed
     */
    public function handle()
    {
        //
        //  Push a job for all accounts scheduled for billing
        //
        $toBill = Billing::where('billing_period_ends_at', '<=', now())
                         ->whereNull('locked_at')
                         ->limit(200)
                         ->get();

        Billing::whereIn('id', array_column($toBill->toArray(), 'id'))
                ->update(['locked_at' => now()]);

        foreach($toBill as $billing){
            BillAccountJob::dispatch($billing);
        }
    }
}
