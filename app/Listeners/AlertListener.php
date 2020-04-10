<?php

namespace App\Listeners;

use App\Events\AlertEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

class AlertListener
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
     * @param  AlertEvent  $event
     * @return void
     */
    public function handle(AlertEvent $event)
    {
        $user = $event->user;
        $accountOnlineUsers = Cache::get('websockets.accounts.' . $user->account_id) ?: [];
        if( count($accountOnlineUsers) ){
            $connectionInfo = $accountOnlineUsers[$user->id] ?? null ;
            if( $connectionInfo ){
                $package = [
                    'to'      => $event->user->id,
                    'content' =>  [
                        'type' => 'Alert',
                        'data' => $event->alert
                    ]
                ];
                $this->pushSocketData($connectionInfo['host'], $package);
            }
        }
    }
}
