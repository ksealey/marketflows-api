<?php

namespace App\Listeners\Company;

use App\Events\Company\PhoneNumberConfigEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

class PhoneNumberConfigListener
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
     * @param  PhoneNumberConfigEvent  $event
     * @return void
     */
    public function handle(PhoneNumberConfigEvent $event)
    {
        $user = $event->user;
        $accountOnlineUsers = Cache::get('websockets.accounts.' . $user->account_id) ?: [];
        if( count($accountOnlineUsers) ){
            foreach( $accountOnlineUsers as $connectionInfo ){
                if( $connectionInfo ){
                    $package = [
                        'to'      => $connectionInfo['user_id'],
                        'type'    => 'PhoneNumberConfig',
                        'action'  => $event->action,
                        'content' => $event->phoneNumberConfigs
                    ];
                    $this->pushSocketData($connectionInfo['host'], $package);
                }
            }            
        }
    }
}
