<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Tests;

use Clue\Redis\Server\Factory;
use Clue\Redis\Server\Server;
use React\EventLoop\StreamSelectLoop;

class FactoryTest extends TestCase
{
    private ?StreamSelectLoop $loop;

    private ?Factory $factory;

    public function setUp(): void
    {
        $this->loop = new StreamSelectLoop();
        $this->factory = new Factory($this->loop);
    }

    public function testPairAuthRejectDisconnects(): void
    {
        if (defined('HPHP_VERSION')) {
            static::markTestSkipped();
        }

        $server = null;

        // bind to a random port on the local interface
        $address = '127.0.0.1:0';

        // start a server that only sends ERR messages.
        $this->factory->createServer($address)->then(function (Server $s) use (&$server): void {
            $server = $s;
        });

        static::assertNotNull($server, 'Server instance must be set by now');
        static::assertNotNull($server->getLocalAddress());

        // we expect a single single client
        $server->on('connection', $this->expectCallableOnce());

        // we expect the client to close the connection once he receives an ERR messages.
        $server->on('disconnection', $this->expectCallableOnce());

        // end the loop (stop ticking)
        $server->on('disconnection', function () use ($server): void {
            $server->close();
        });

        // we expect the factory to fail because of the ERR message.
        $stream = stream_socket_client($server->getLocalAddress());
        fwrite($stream, "invalid\r\n");
        fclose($stream);

        $this->loop->run();
    }

    public function testServerAddressInvalidFail(): void
    {
        $promise = $this->factory->createServer('invalid address');

        $this->expectPromiseReject($promise);
    }

    public function testServerAddressInUseFail(): void
    {
        $this->factory->createServer('tcp://localhost:6379')->then(function (): void {
            $promise = $this->factory->createServer('tcp://localhost:6379');

            $this->expectPromiseReject($promise);
        });
    }
}
