<?php
namespace ngyuki\React\WebSocket;

use React\EventLoop\LoopInterface;
use React\Socket\ServerInterface as SocketServerInterface;
use React\Socket\ConnectionInterface;
use Evenement\EventEmitter;

class Server extends EventEmitter
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var SocketServerInterface
     */
    private $socket;

    public function __construct(LoopInterface $loop, SocketServerInterface $socket)
    {
        $this->loop = $loop;
        $this->socket = $socket;

        $this->socket->on('connection', function (ConnectionInterface $conn) {
            new Handshake($this->loop, $this, $conn);
        });
    }
}
