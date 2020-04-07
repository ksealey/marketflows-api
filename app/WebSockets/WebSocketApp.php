<?php
namespace App\WebSockets;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use App\Models\User;
use Exception;
use Cache;

class WebSocketApp implements MessageComponentInterface
{
    protected $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
    }

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
        $conn->user = $user;
        $ip         = getHostByName(php_uname('n'));

        //  Start notifications for user
        Cache::tags(['websockets'])->put('websockets.users.' . $user->id, [
            'user_id' => $user->id,
            'host'    => $ip
        ]);

        $this->clients->attach($conn);

        echo "\nConnection for user {$user->id} established.";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $message = json_decode($msg);
        if( ! $message ) return;
        
       
        
    }

    public function onClose(ConnectionInterface $conn)
    {
        //  End notifications for user
        $user = $conn->user;
        Cache::forget('websockets.users.' . $user->id);

        $this->client->detach($conn);

        echo "\nConnection for user {$user->id} closed.";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error was encountered. " . $e->getMessage();

        $conn->close();
    }
}