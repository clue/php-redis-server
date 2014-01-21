<?php

namespace Clue\Redis\React;

use React\Socket\Server as ServerSocket;
use React\Promise\When;
use React\EventLoop\LoopInterface;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use Clue\Redis\React\Server\Server;
use Clue\Redis\Protocol\Factory as ProtocolFactory;
use InvalidArgumentException;
use BadMethodCallException;
use Exception;

class Factory
{
    private $loop;
    private $connector;
    private $protocol;

    public function __construct(LoopInterface $loop, ProtocolFactory $protocol = null)
    {
        $this->loop = $loop;

        if ($protocol === null) {
            $protocol = new ProtocolFactory();
        }
        $this->protocol = $protocol;
    }

    public function createServer($address)
    {
        $parts = $this->parseUrl($address);

        $socket = new ServerSocket($this->loop);
        try {
            $socket->listen($parts['port'], $parts['host']);
        }
        catch (Exception $e) {
            return When::reject($e);
        }

        return When::resolve(new Server($socket, $this->loop, $this->protocol));
    }

    private function parseUrl($target)
    {
        if ($target === null) {
            $target = 'tcp://127.0.0.1';
        }
        if (strpos($target, '://') === false) {
            $target = 'tcp://' . $target;
        }

        $parts = parse_url($target);
        if ($parts === false || !isset($parts['host']) || $parts['scheme'] !== 'tcp') {
            throw new Exception('Given URL can not be parsed');
        }

        if (!isset($parts['port'])) {
            $parts['port'] = '6379';
        }

        if ($parts['host'] === 'localhost') {
            $parts['host'] = '127.0.0.1';
        }

        return $parts;
    }
}
