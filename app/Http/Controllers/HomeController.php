<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Web3\Web3;
use Web3\Providers\HttpProvider;
use Web3\RequestManagers\HttpRequestManager;
use React\EventLoop\Factory;
use React\Socket\Server;
use React\Socket\ConnectionInterface;
use WSSC\WebSocketClient;
use \WSSC\Components\ClientConfig;
use App\ServerHandler;
use WebSocket\Client;
use Ratchet\WebSocket\WsServer;
use Ratchet\Http\HttpServer;
use Socket\Soc;
use App\Webs;
use Amp\Delayed;
use Amp\Websocket\Client\Connection;
use Amp\Websocket\Message;
use function Amp\Websocket\Client\connect;
use Amp\Websocket;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {


        \Amp\Loop::run(function () {
            /** @var Connection $connection */
            $connection = yield Websocket\connect('wss://ws.blockchain.info/inv');
            yield $connection->send('{"op" : "ping_block"}');

            $i = 0;

            while ($message = yield $connection->receive()) {
                /** @var Message $message */
                $payload = yield $message->buffer();
                dump($payload);
                printf("Received: %s\n", $payload);

                if ($payload === "Goodbye!") {
                    $connection->close();
                    break;
                }

                yield new Delayed(1000);

                if ($i < 3) {
                    yield $connection->send("Ping: " . ++$i);
                } else {
                    yield $connection->send("Goodbye!");
                }
            }
        });


//        \Ratchet\Client\connect('wss://ws.blockchain.info/inv')->then(function($conn) {
//            $conn->on('message', function($msg) use ($conn) {
//                echo "Received: {$msg}\n";
//                dump($msg);
//
//                $conn->close();
//            });
//            $conn->on('close', function($code = null, $reason = null) {
//                echo "Connection closed ({$code} - {$reason})\n";
//            });
//
//            $conn->send(['op'=>'unconfirmed_sub']);
//        }, function ($e) {
//            echo "Could not connect: {$e->getMessage()}\n";
//        });


//        $client = new Client("wss://ws.blockchain.info/inv",array('timeout' => 200));
//
//        while (1) {
//            $client->send('{"op" : "blocks_sub"}');
//
//            $message = $client->receive();
//            if ($message) {
//                $messageObject = json_decode($message);
//                dump($messageObject);
//
//                //do something, such as print to console
//            }
//        }

//        $sock = socket_create(AF_INET, SOCK_STREAM, getprotobyname('ssl'));
//        socket_bind($sock, 'wss://ws.blockchain.info/inv', 5000);
//        socket_listen($sock,1);
//
//        dump($sock);
////        socket_bind($sock, '127.0.0.1',5000);
////        socket_listen($sock,1);
////        sleep(20);
//
//
////        if (socket_bind($socket, $host) === false) {


    }


}
