<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\Router;
use App\WebSockets\WebSocketApp;
use App\WebSockets\Http\Auth as HttpAuth;
use App\WebSockets\Controllers\AlertController;

class ServerWS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'serve-ws {--port=}';

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
        $ws       = new WsServer(new WebSocketApp());
        $http     = new HttpServer($ws);
        $server   = IoServer::factory(
            $http,
            $this->option('port') ?: env('WS_PORT', 8080)
        );
        $server->run();
    }
}
