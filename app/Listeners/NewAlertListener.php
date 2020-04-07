<?php

namespace App\Listeners;

use App\Events\NewAlertEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Cache;
use ZMQContext;
use ZMQ;

class NewAlertListener
{
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
     * @param  NewAlertEvent  $event
     * @return void
     */
    public function handle(NewAlertEvent $event)
    {
        if( $connectionInfo = Cache::get('websockets.users.' . $event->user->id) ){
            $package = [
                'to'      => $event->user->id,
                'content' =>  [
                    'type' => 'Alert',
                    'data' => $event->alert
                ]
            ];
            $context = new ZMQContext();
            $socket  = $context->getSocket(ZMQ::SOCKET_PUSH);
            $socket->connect('tcp://' . $connectionInfo['host'] . ':' . env('WS_PULL_PORT', 5555));
            $socket->send(json_encode($package));
        }
    }
}
