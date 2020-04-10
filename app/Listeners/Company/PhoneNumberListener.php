<?php

namespace App\Listeners\Company;

use App\Events\Company\PhoneNumberEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Company\BankedPhoneNumber;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

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
        //  Move numbers to bank, release if needed
        //
        //
        // ...

        $user = $event->user;
        $accountOnlineUsers = Cache::get('websockets.accounts.' . $user->account_id) ?: [];
        if( count($accountOnlineUsers) ){
            $connectionInfo = $accountOnlineUsers[$user->id] ?? null ;
            if( $connectionInfo ){
                $package = [
                    'to'      => $event->user->id,
                    'type'    => 'PhoneNumber',
                    'action'  => $event->action,
                    'content' => $event->phoneNumbers
                ];
                $this->pushSocketData($connectionInfo['host'], $package);
            }
        }


    }
}
