<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use \React\EventLoop\Factory as ReactEventLoopFactory;
use \React\ZMQ\Context as ReactZMQContext;
use \React\Socket\Server as ReactSocketServer;
use App\WebSockets\WebSocketApp;
use ZMQ;
use ZMQSocket;

class ServerWS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'serve-ws';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start websocket server';

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
        $app = new WebSocketApp();

        // Listen for pushes
        $eventLoop = ReactEventLoopFactory::create();
        $context   = new ReactZMQContext($eventLoop);
        $pull      = $context->getSocket(ZMQ::SOCKET_PULL);
        $pullPort  = config('websockets.pull.port');
        $pull->bind('tcp://0.0.0.0:' . $pullPort); // Allow connections from anywhere on port
        $pull->on('message', array($app, 'onEvent'));

        // Set up our WebSocket server for clients wanting real-time updates
        $socketPort = config('websockets.serve.port');
        $webSocket  = new ReactSocketServer('0.0.0.0:' . $socketPort, $eventLoop); // Binding to 0.0.0.0 means remotes can connect
        $webServer  = new IoServer(
            new HttpServer(
                new WsServer($app)
            ),
            $webSocket
        );

        $eventLoop->run();
    }
}
