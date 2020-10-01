<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Tests\Server\Business;

use Clue\Redis\Server\Business\Lists;
use Clue\Redis\Server\Storage;
use Clue\Redis\Server\Tests\TestCase;

class ListsTest extends TestCase
{
    private $business;

    private $storage;

    public function setUp(): void
    {
        $this->storage = new Storage();
        $this->business = new Lists($this->storage);
    }

    public function testList(): void
    {
        static::assertEquals(0, $this->business->llen('list'));

        static::assertEquals(1, $this->business->rpush('list', 'b'));
        static::assertEquals(2, $this->business->rpush('list', 'c'));
        static::assertEquals(3, $this->business->lpush('list', 'a'));

        static::assertEquals(3, $this->business->llen('list'));

        static::assertEquals('c', $this->business->rpop('list'));
        static::assertEquals('a', $this->business->lpop('list'));
        static::assertEquals('b', $this->business->lpop('list'));

        static::assertNull($this->business->lpop('list'));

        static::assertFalse($this->storage->hasKey('list'));

        static::assertEquals(1, $this->business->rpush('list', 'a'));
        static::assertEquals('a', $this->business->rpop('list'));
        static::assertNull($this->business->rpop('list'));

        static::assertFalse($this->storage->hasKey('list'));
    }

    public function testLpushOrder(): void
    {
        static::assertEquals(3, $this->business->lpush('list', 'a', 'b', 'c'));
        static::assertEquals('c', $this->business->lpop('list'));
        static::assertEquals('b', $this->business->lpop('list'));
        static::assertEquals('a', $this->business->lpop('list'));
        static::assertNull($this->business->lpop('list'));
    }

    public function testPushX(): void
    {
        static::assertEquals(0, $this->business->lpushx('list', 'a'));
        static::assertEquals(0, $this->business->rpushx('list', 'b'));
        static::assertFalse($this->storage->hasKey('list'));

        static::assertEquals(1, $this->business->lpush('list', 'c'));

        static::assertEquals(2, $this->business->lpushx('list', 'd'));
        static::assertEquals(3, $this->business->rpushx('list', 'e'));

        static::assertEquals('d', $this->business->lpop('list'));
        static::assertEquals('c', $this->business->lpop('list'));
        static::assertEquals('e', $this->business->lpop('list'));
    }

    public function testRpopLpush(): void
    {
        static::assertNull($this->business->rpoplpush('a', 'b'));
        static::assertFalse($this->storage->hasKey('b'));

        static::assertEquals(3, $this->business->rpush('a', '1', '2', '3'));

        static::assertEquals('3', $this->business->rpoplpush('a', 'b'));
        static::assertTrue($this->storage->hasKey('b'));

        static::assertEquals('2', $this->business->rpoplpush('a', 'b'));
        static::assertEquals('1', $this->business->rpoplpush('a', 'b'));

        static::assertFalse($this->storage->hasKey('a'));
    }

    public function testLindex(): void
    {
        static::assertNull($this->business->lindex('list', 1));

        $this->business->rpush('list', 'a', 'b', 'c');

        static::assertEquals('a', $this->business->lindex('list', 0));
        static::assertEquals('c', $this->business->lindex('list', 2));
        static::assertEquals('c', $this->business->lindex('list', -1));
        static::assertEquals('a', $this->business->lindex('list', -3));

        static::assertNull($this->business->lindex('list', 3));
        static::assertNull($this->business->lindex('list', -4));
    }

    public function testLrange(): void
    {
        static::assertEquals([], $this->business->lrange('list', 0, 100));

        $this->business->rpush('list', 'a', 'b', 'c', 'd');

        static::assertEquals(['b', 'c'], $this->business->lrange('list', 1, 2));
        static::assertEquals(['c', 'd'], $this->business->lrange('list', 2, 100));
        static::assertEquals(['a'], $this->business->lrange('list', 0, 0));
        static::assertEquals(['d'], $this->business->lrange('list', -1, -1));

        static::assertEquals([], $this->business->lrange('list', 2, 1));
        static::assertEquals([], $this->business->lrange('list', 100, 200));
    }
}
