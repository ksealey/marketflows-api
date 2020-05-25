<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BankedPhoneNumber;
use App\Helpers\PhoneNumberManager;
use App;

class ReleaseToBeRenewedBankedPhoneNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'banked-phone-numbers:release-to-be-renewed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release banked phone numbers that will be renewed soon';

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
        $numberManager = App::make(PhoneNumberManager::class);
        $twoDaysInTheFuture = now()->addDays(2);
        $releases           = BankedPhoneNumber::where('release_by', '<=', $twoDaysInTheFuture)->get();

        foreach( $releases as $release ){
            $numberManager->releaseNumber($release->external_id);
            $release->delete();

            usleep(250); // Throttle to 4 a second
        }
    }
}
