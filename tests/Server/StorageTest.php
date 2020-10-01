<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Tests\Server;

use Clue\Redis\Server\InvalidDatatypeException;
use Clue\Redis\Server\Storage;
use Clue\Redis\Server\Tests\TestCase;

class StorageTest extends TestCase
{
    private $storage;

    public function setUp(): void
    {
        $this->storage = new Storage();
    }

    public function testHasSetUnset(): void
    {
        static::assertFalse($this->storage->hasKey('key'));

        $this->storage->setString('key', 'value');
        static::assertTrue($this->storage->hasKey('key'));

        $this->storage->unsetKey('key');
        static::assertFalse($this->storage->hasKey('key'));
    }

    public function testString(): void
    {
        static::assertNull($this->storage->getStringOrNull('key'));

        $this->storage->setString('key', 'value');

        static::assertEquals('value', $this->storage->getStringOrNull('key'));
    }

    public function testList(): void
    {
        // empty list will be created on first access
        $list = $this->storage->getOrCreateList('list');
        static::assertInstanceOf('SplDoublyLinkedList', $list);
        static::assertTrue($list->isEmpty());

        // further calls return the same list
        static::assertSame($list, $this->storage->getOrCreateList('list'));
    }

    public function testKeys(): void
    {
        static::assertEquals([], $this->storage->getAllKeys());
        static::assertNull($this->storage->getRandomKey());

        $this->storage->setString('a', '1');
        $this->storage->setString('b', '2');

        static::assertEquals(['a', 'b'], $this->storage->getAllKeys());

        $this->storage->setTimeout('b', 0);

        static::assertEquals(['a'], $this->storage->getAllKeys());
        static::assertEquals('a', $this->storage->getRandomKey());
    }

    public function testInvalidList(): void
    {
        $this->expectException(InvalidDatatypeException::class);
        $this->storage->setString('string', 'value');
        $this->storage->getOrCreateList('string');
    }

    public function testInvalidString(): void
    {
        $this->expectException(InvalidDatatypeException::class);
        $this->storage->getOrCreateList('list');
        $this->storage->getStringOrNull('list');
    }
}
