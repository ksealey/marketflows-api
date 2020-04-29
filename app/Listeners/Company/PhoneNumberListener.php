<?php

namespace App\Listeners\Company;

use App\Events\Company\PhoneNumberEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Company\BankedPhoneNumber;
use App\Models\Company\Call;
use App\WebSockets\Traits\PushesSocketData;
use Illuminate\Support\Carbon;
use Cache;
use DateTime;

class PhoneNumberListener
{
    use PushesSocketData;
    
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  PhoneNumberEvent  $event
     * @return void
     */
    public function handle(PhoneNumberEvent $event)
    {
        if( $event->action !== 'delete' )
            return;

        //  Move numbers to bank, release if needed
        $inserts          = [];
        $releases         = [];
        $today            = today();
        $sevenDaysAgo     = today()->subDays(7);
        $threeDaysFromNow = today()->addDays(3);
        $callThreshold    = 21; // 3 calls per day for 7 days

        foreach( $event->phoneNumbers as $phoneNumber ){
            //  Determine enewal date
            $purchaseDate = new Carbon($phoneNumber->purchased_at);
            $renewDate    = new Carbon($today->format('Y-m-' . $purchaseDate->format('d')));
            if( $today >= $renewDate ) // If renew date has passed for month, move to next month
                $renewDate->addMonths('1');

            //  Determine call count for last 7 days
            $calls = Call::where('phone_number_id', $phoneNumber->id)
                        ->where('created_at', '>=', $sevenDaysAgo)
                        ->get();

            $daysUntilRenew = $today->diffInDays($renewDate);
            if( $daysUntilRenew <= 3 ){
                $releases[] = $phoneNumber;
                continue;
            }

            $releaseBy = $renewDate->subDays(2);
            $inserts[] = [
                'external_id'            => $phoneNumber->external_id,
                'country'                => $phoneNumber->country,
                'country_code'           => $phoneNumber->country_code,
                'number'                 => $phoneNumber->number,
                'voice'                  => $phoneNumber->voice,
                'sms'                    => $phoneNumber->sms,
                'mms'                    => $phoneNumber->mms,
                'type'                   => $phoneNumber->type,
                'calls'                  => count($calls),
                'purchased_at'           => $phoneNumber->purchased_at,
                'release_by'             => $releaseBy,
                'released_by_account_id' => $event->user->account_id,
                'status'                 => count($calls) > $callThreshold ? 'Banked' : 'Available',
                'created_at'             => now(),
                'updated_at'             => now()
            ];
        }

        if( count($inserts) ){
            BankedPhoneNumber::insert($inserts);
        }

        if( count($releases) ){
            foreach( $releases as $phoneNumber ){
                $phoneNumber->release();
            }
        }
    }
}
