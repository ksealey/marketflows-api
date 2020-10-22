<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Auth\PaymentSetup;
use App\Helpers\PaymentManager;
use App;

class ClearExpiredPaymentSetups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear-expired-payment-setups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear expired payment setups';

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
        $paymentManager = App::make(PaymentManager::class);

        PaymentSetup::where('expires_at', '<', now())
                    ->get()
                    ->each(function($setup) use($paymentManager){
                        try{
                            $setup->delete();
                            $paymentManager->deleteCustomer($setup->customer_id);
                        }catch(\Exception $e){}
                    });

        return 0;
    }
}
