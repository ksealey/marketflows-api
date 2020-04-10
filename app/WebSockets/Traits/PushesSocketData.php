<?php
namespace App\WebSockets\Traits;

use ZMQContext;
use ZMQ;
use Exception;
use Log;

trait PushesSocketData
{
    public function pushSocketData($host, $package)
    {
        try{
            $context = new ZMQContext();
            $socket  = $context->getSocket(ZMQ::SOCKET_PUSH);
            $socket->connect('tcp://' . $host . ':' . config('websockets.pull.port'));
            $socket->send(json_encode($package));
            
            return true;
        }catch(Exception $e){
            Log::error($e->getTraceAsString());

            return false;
        }
    }
    
}