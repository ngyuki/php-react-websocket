<?php
namespace ngyuki\Example;

use React\EventLoop\Factory;
use React\Socket\Server;
use React\Socket\ConnectionInterface;
use Wrench\Payload\PayloadHandler;
use Wrench\Payload\Payload;
use Wrench\Protocol\Rfc6455Protocol;
use Wrench\Frame\HybiFrame;

require __DIR__ . '/../vendor/autoload.php';

$loop = Factory::create();
$socket = new Server($loop);

$socket->on('connection', function (ConnectionInterface $conn) use ($socket) {
    $buffer = "";

    $conn->once('data', $handshake = function ($data) use ($socket, $conn, &$handshake, &$buffer) {
        $buffer .= $data;

        if (strpos($buffer, "\r\n\r\n") === false)
        {
            $conn->once('data', $handshake);
        }
        else
        {
            try
            {
                $protocol = new Rfc6455Protocol();
                list (/*$path*/, /*$origin*/, $key, /*$extensions*/, /*$proto*/, /*$headers*/, /*$params*/)
                    = $protocol->validateRequestHandshake($buffer);

                $conn->write($protocol->getResponseHandshake($key));
                $socket->emit('ws:connection', [$conn]);
            }
            catch (\Exception $ex)
            {
                $conn->close();
            }
        }
    });
});

/* @var $clients \SplObjectStorage | ConnectionInterface[] */
$clients = new \SplObjectStorage();

$socket->on('ws:connection', function (ConnectionInterface $client) use ($loop, $clients) {
    $clients->attach($client);

    $handler = new PayloadHandler(function (Payload $payload) use ($client) {
        switch ($payload->getType())
        {
            case Rfc6455Protocol::TYPE_TEXT:
                $client->emit('ws:data', [$payload->getPayload()]);
                break;

            case Rfc6455Protocol::TYPE_PING:
                $client->emit('ws:ping', [$payload->getPayload()]);
                break;

            case Rfc6455Protocol::TYPE_CLOSE:
                $client->emit('ws:close');
                break;
        }
    });

    $client->on('data', function ($data) use ($handler) {
        $handler->handle($data);
    });

    $client->on('ws:data', function ($data) use ($clients, $client) {
        $data = json_decode($data);

        $self = json_encode(['message' => $data->message, 'type' => 'self']);
        $other = json_encode(['message' => $data->message]);

        foreach ($clients as $target)
        {
            $frame = new HybiFrame();
            $frame->encode($target === $client ? $self : $other);
            $target->write($frame->getFrameBuffer());
        }
    });

    $client->on('ws:ping', function ($data) use ($client) {
        $frame = new HybiFrame();
        $frame->encode($data, Rfc6455Protocol::TYPE_PONG, false);
        $client->write($frame->getFrameBuffer());
    });

    $client->on('ws:close', function () use ($loop, $client) {
        $loop->nextTick(function () use ($client) {
            $client->close();
        });
    });

    $client->on('end', function () use ($clients, $client) {
        $clients->detach($client);
    });
});

$socket->listen(4444, '0.0.0.0');
$loop->run();
