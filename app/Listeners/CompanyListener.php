<?php

namespace App\Listeners;

use App\Events\CompanyEvent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\WebSockets\Traits\PushesSocketData;
use Cache;

class CompanyListener
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
     * @param  CompanyEvent  $event
     * @return void
     */
    public function handle(CompanyEvent $event)
    {
        $user = $event->user;
        $accountOnlineUsers = Cache::get('websockets.accounts.' . $user->account_id) ?: [];
        if( count($accountOnlineUsers) ){
            foreach( $accountOnlineUsers as $connectionInfo ){
                if( $connectionInfo ){
                    $package = [
                        'to'      => $connectionInfo['user_id'],
                        'type'    => 'Company',
                        'action'  => $event->action,
                        'content' => $event->companies
                    ];
                    $this->pushSocketData($connectionInfo['host'], $package);
                }
            }            
        }
    }
}
