<?php

namespace Clue\Redis\Server;

use React\Socket\Server as ServerSocket;
use React\Promise\Deferred;
use React\EventLoop\LoopInterface;
use React\SocketClient\ConnectorInterface;
use React\Stream\Stream;
use Clue\Redis\Server\Server;
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

        $deferred = new Deferred();

        $socket = new ServerSocket($this->loop);
        try {
            $socket->listen($parts['port'], $parts['host']);
            $deferred->resolve(new Server($socket, $this->loop, $this->protocol));
        }
        catch (Exception $e) {
            $deferred->reject($e);
        }

        return $deferred->promise();
    }

    private function parseUrl($target)
    {
        if (strpos($target, '://') === false) {
            $target = 'tcp://' . $target;
        }

        // parse_url() does not accept null ports (random port assignment) => manually remove
        $nullport = false;
        if (substr($target, -2) === ':0') {
            $target = substr($target, 0, -2);
            $nullport = true;
        }

        $parts = parse_url($target);
        if ($parts === false || !isset($parts['host']) || $parts['scheme'] !== 'tcp') {
            throw new Exception('Given target URL "' . $target . '" can not be parsed');
        }

        if ($nullport) {
            $parts['port'] = 0;
        } elseif (!isset($parts['port'])) {
            $parts['port'] = '6379';
        }

        if ($parts['host'] === 'localhost') {
            $parts['host'] = '127.0.0.1';
        }

        return $parts;
    }
}
