<?php

namespace Socket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Soc  {

    public function __construct($a) {
        $client = new Client("ws://echo.websocket.org/");
        $client->send("Hello WebSocket.org!");

         dump($client->receive()); // Will output 'Hello WebSocket.org!'
    }
}