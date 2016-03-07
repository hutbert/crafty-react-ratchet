<?php

require 'vendor/autoload.php';
require 'http.php';
require 'ws.php';

$ip = (isset($argv[1])) ? $argv[1] : "127.0.0.1";
$hostname = (isset($argv[2])) ? $argv[2] : "localhost";


$loop = React\EventLoop\Factory::create();


$http_socket = new React\Socket\Server($loop);
$http = new React\Http\Server($http_socket);
$http->on('request', [ new Http(), "onRequest" ]);
$http_socket->listen(8000, $ip);

echo "Web server active at http://localhost:8000/\n";


$app = new Ratchet\App($hostname, 8080, $ip, $loop);
$app->route("/ws", new Ws());

echo "Websocket server active at ws://localhost:8080/ws\n";

$app->run();
