<?php
namespace App\WebSockets;

use Ratchet\Wamp\WampServerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use App\Models\User;
use Exception;
use Cache;

class WebSocketApp implements MessageComponentInterface
{
    public function __construct()
    {
        $this->clients = [];
    }

    /**
     * Handle an incoming websocket connection
     * 
     */
    public function onOpen(ConnectionInterface $conn)
    {
        $query = $conn->httpRequest->getUri()->getQuery();
        if( ! $query ){
            echo "\nAuthentication required.";
            return $conn->close();
        }

        $params = explode('&', $query);
        if( ! count($params) ){
            echo "\nAuthentication required.";
            return $conn->close();
        }

        $authToken = null;
        foreach( $params as $param ){
            if( preg_match('/^auth_token=/', $param) ){
                $authToken = str_replace('auth_token=', '', $param);
            }
        }
        if( ! $authToken ){
            echo "\nAuthentication required.";
            return $conn->close();
        }
       
        $user = User::where('auth_token', $authToken)->first();
        if( ! $user ){
            echo "\nAuthentication invalid.";
            return $conn->close();
        }

        //  Add user to connection
        $conn->user = json_decode(json_encode($user));
        $ip         = getHostByName(php_uname('n'));

        //  Start notifications for user, using account
        $cacheKey                = 'websockets.accounts.' . $user->account_id;
        $accountUsers            = Cache::get($cacheKey) ?: [];
        $accountUsers[$user->id] = [
            'user_id'  => $user->id,
            'host'     => $ip,
            'datetime' => date('Y-m-d H:i:s')
        ];

        Cache::put($cacheKey, $accountUsers);

        $this->clients[$user->id] = $conn;
    }

    public function onMessage(ConnectionInterface $conn, $message)
    {

    }

    public function onEvent($data)
    {   
        $package = json_decode($data);
        $conn    = $this->clients[$package->to] ?? null;
        if( $conn ){
            $conn->send(json_encode($package));
        }
    }

    public function onError(ConnectionInterface $conn, Exception $e)
    {
        echo $e->getMessage();

        $conn->close();
    }

    public function onClose(ConnectionInterface $conn)
    {
        $cacheKey     = 'websockets.accounts.' . $conn->user->account_id;
        $accountUsers = Cache::get($cacheKey) ?: [];
        $accountUsers = array_filter($accountUsers, function($user) use($conn){
            return $conn->user->id != $user->id;
        });

        if( count($accountUsers) ){
            Cache::put($cacheKey, $accountUsers);
        }else{
            Cache::remove($cacheKey);
        }

        unset($this->clients[$conn->user->id]);
    }
}