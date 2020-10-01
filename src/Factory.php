<?php

declare(strict_types=1);

namespace Clue\Redis\Server;

use Clue\Redis\Protocol\Factory as ProtocolFactory;
use Exception;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\Server as ServerSocket;

class Factory
{
    private LoopInterface $loop;

    private ProtocolFactory $protocol;

    public function __construct(LoopInterface $loop, ?ProtocolFactory $protocol = null)
    {
        $this->loop = $loop;
        $this->protocol = $protocol ?? new ProtocolFactory();
    }

    public function createServer(string $address): PromiseInterface
    {
        $parts = $this->parseUrl($address);

        $deferred = new Deferred();

        $socket = new ServerSocket($this->loop);
        try {
            $socket->listen($parts['port'], $parts['host']);
            $deferred->resolve(new Server($socket, $this->loop, $this->protocol));
        } catch (Exception $e) {
            $deferred->reject($e);
        }

        return $deferred->promise();
    }

    private function parseUrl(string $target): array
    {
        if (mb_strpos($target, '://') === false) {
            $target = 'tcp://' . $target;
        }

        // parse_url() does not accept null ports (random port assignment) => manually remove
        $nullport = false;
        if (mb_substr($target, -2) === ':0') {
            $target = mb_substr($target, 0, -2);
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
