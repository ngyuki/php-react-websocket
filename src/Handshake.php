<?php
namespace ngyuki\React\WebSocket;

use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use Evenement\EventEmitterInterface;
use Wrench\Protocol\Rfc6455Protocol;
use Wrench\Exception\HandshakeException;

class Handshake
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var EventEmitterInterface
     */
    private $server;

    /**
     * @var ConnectionInterface
     */
    private $conn;

    /**
     * @var string
     */
    private $buffer = "";

    public function __construct(LoopInterface $loop, EventEmitterInterface $server, ConnectionInterface $conn)
    {
        $this->loop = $loop;
        $this->server = $server;
        $this->conn = $conn;

        $this->conn->once('data', function ($data) {
            $this->handshake($data);
        });
    }

    private function handshake($data)
    {
        if ($this->recv($data) == false)
        {
            $this->conn->once('data', function ($data) {
                $this->handshake($data);
            });
        }
        else
        {
            $this->complete();
        }
    }

    private function recv($data)
    {
        $this->buffer .= $data;

        if (strpos($this->buffer, "\r\n\r\n") !== false)
        {
            return true;
        }

        return false;
    }

    private function complete()
    {
        $protocol = new Rfc6455Protocol();

        try
        {
            list ($path, $origin, $key, $extensions, $proto, $headers, $params)
                = $protocol->validateRequestHandshake($this->buffer);

            $this->server->emit('accept', [
                $path, $origin, $key, $extensions, $proto, $headers, $params
            ]);

            $response = $protocol->getResponseHandshake($key);

            $this->conn->write($response);

            $this->server->emit('connection', [
                new Client($this->loop, $this->conn)
            ]);
        }
        catch (HandshakeException $ex)
        {
            $response = $protocol->getResponseError($ex);
            $this->conn->write($response);

            $this->loop->nextTick(function () {
                $this->conn->close();
            });
        }
        catch (\Exception $ex)
        {
            $this->loop->nextTick(function () {
                $this->conn->close();
            });
        }
    }
}
