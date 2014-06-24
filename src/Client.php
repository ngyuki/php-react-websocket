<?php
namespace ngyuki\React\WebSocket;

use React\Socket\ConnectionInterface;
use React\EventLoop\LoopInterface;
use Evenement\EventEmitter;
use Wrench\Payload\PayloadHandler;
use Wrench\Payload\Payload;
use Wrench\Frame\HybiFrame;
use Wrench\Protocol\Rfc6455Protocol;

class Client extends EventEmitter
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var ConnectionInterface
     */
    private $conn;

    public function __construct(LoopInterface $loop, ConnectionInterface $conn)
    {
        $this->loop = $loop;
        $this->conn = $conn;

        $handler = new PayloadHandler(function (Payload $payload) {
            switch ($payload->getType())
            {
                case Rfc6455Protocol::TYPE_CLOSE:
                    $this->emit('disconnect');
                    break;

                case Rfc6455Protocol::TYPE_PING:
                    $this->emit('ping', [$payload->getPayload()]);
                    break;

                case Rfc6455Protocol::TYPE_TEXT:
                    $this->emit('data', [$payload->getPayload()]);
                    break;
            }
        });

        $this->conn->on('data', function ($data) use ($handler) {
            $handler->handle($data);
        });

        $this->conn->on('end', function() {
            $this->emit('end');
        });

        $this->on('ping', function ($data) {
            $frame = new HybiFrame();
            $frame->encode($data, Rfc6455Protocol::TYPE_PONG, false);
            $this->conn->write($frame->getFrameBuffer());
        });

        $this->on('disconnect', function () {
            $this->loop->nextTick(function () {
                $this->conn->close();
            });
        });
    }

    public function send($data)
    {
        $frame = new HybiFrame();
        $frame->encode($data);
        $this->conn->write($frame->getFrameBuffer());
    }

    public function getRemoteAddress()
    {
        return $this->conn->getRemoteAddress();
    }
}
