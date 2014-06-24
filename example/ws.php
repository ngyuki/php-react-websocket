<?php
namespace ngyuki\Example;

use React\EventLoop\Factory;
use React\Socket\Server as SocketServer;
use ngyuki\React\WebSocket\Server as WebSocketServer;
use ngyuki\React\WebSocket\Client as WebSocketClient;
use React\Http\Server as HttpServer;
use React\Http\Request;
use React\Http\Response;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$wsSocket = new SocketServer($loop);
$wsServer = new WebSocketServer($loop, $wsSocket);

/* @var $clients \SplObjectStorage | WebSocketClient[] */
$clients = new \SplObjectStorage();

$wsServer->on('connection', function (WebSocketClient $client) use ($clients) {
    $clients->attach($client);

    $addr = $client->getRemoteAddress();
    echo "[$addr] connect\n";

    $data = json_encode(['message' => "welcome $addr", 'type' => 'info']);

    foreach ($clients as $target) {
        $target->send($data);
    }

    $client->on('data', function ($data) use ($clients, $client, $addr) {
        $len = strlen($data);
        echo "[$addr] data $len byte\n";

        $data = json_decode($data);

        $self = json_encode(['message' => $data->message, 'type' => 'self']);
        $other = json_encode(['message' => $data->message]);

        foreach ($clients as $target) {
            if ($target === $client) {
                $target->send($self);
            } else {
                $target->send($other);
            }
        }
    });

    $client->on('disconnect', function () use ($addr) {
        echo "[$addr] disconnect\n";
    });

    $client->on('end', function () use ($clients, $client, $addr) {
        echo "[$addr] end\n";
        $clients->detach($client);

        $data = json_encode(['message' => "goodbye $addr", 'type' => 'info']);

        foreach ($clients as $target) {
            $target->send($data);
        }
    });
});

$httpSocket = new SocketServer($loop);
$httpServer = new HttpServer($httpSocket);
$httpServer->on('request', function (Request $request, Response $response) {
    $response->writeHead();
    $response->end(file_get_contents(__DIR__ . '/index.html'));
});

$wsSocket->listen(4444, '0.0.0.0');
echo "Listening 4444 port for WebSocket\n";

$httpSocket->listen(8888, '0.0.0.0');
echo "Listening 8888 port for HTTP\n";

$loop->run();
