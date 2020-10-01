<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Tests\Server;

use Clue\Redis\Protocol\Serializer\RecursiveSerializer;
use Clue\Redis\Server\Client;
use Clue\Redis\Server\Invoker;
use Clue\Redis\Server\Storage;
use Clue\Redis\Server\Tests\TestCase;
use React\Socket\Connection;

class ClientTest extends TestCase
{
    private $business;

    private $database;

    private $client;

    public function setUp(): void
    {
        /** @var Connection $connection */
        $connection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();

        $this->business = new Invoker(new RecursiveSerializer());
        $this->database = new Storage();
        $this->client = new Client($connection, $this->business, $this->database);
    }

    public function testBusiness(): void
    {
        static::assertSame($this->business, $this->client->getBusiness());

        $business = new Invoker(new RecursiveSerializer());

        $this->client->setBusiness($business);
        static::assertSame($business, $this->client->getBusiness());
    }

    public function testDatabase(): void
    {
        static::assertSame($this->database, $this->client->getDatabase());

        $database = new Storage();

        $this->client->setDatabase($database);
        static::assertSame($database, $this->client->getDatabase());
    }

    public function testName(): void
    {
        static::assertNull($this->client->getName());
        $this->client->setName('name');
        static::assertEquals('name', $this->client->getName());
    }
}
