<?php

use Clue\Redis\Server\Storage;
use Clue\Redis\Server\Business\Lists;

class ListsTest extends TestCase
{
    private $business;
    private $storage;

    public function setUp()
    {
        $this->storage = new Storage();
        $this->business = new Lists($this->storage);
    }

    public function testList()
    {
        $this->assertEquals(0, $this->business->llen('list'));

        $this->assertEquals(1, $this->business->rpush('list', 'b'));
        $this->assertEquals(2, $this->business->rpush('list', 'c'));
        $this->assertEquals(3, $this->business->lpush('list', 'a'));

        $this->assertEquals(3, $this->business->llen('list'));

        $this->assertEquals('c', $this->business->rpop('list'));
        $this->assertEquals('a', $this->business->lpop('list'));
        $this->assertEquals('b', $this->business->lpop('list'));

        $this->assertNull($this->business->lpop('list'));

        $this->assertFalse($this->storage->hasKey('list'));

        $this->assertEquals(1, $this->business->rpush('list', 'a'));
        $this->assertEquals('a', $this->business->rpop('list'));
        $this->assertEquals(null, $this->business->rpop('list'));

        $this->assertFalse($this->storage->hasKey('list'));
    }

    public function testLpushOrder()
    {
        $this->assertEquals(3, $this->business->lpush('list', 'a', 'b', 'c'));
        $this->assertEquals('c', $this->business->lpop('list'));
        $this->assertEquals('b', $this->business->lpop('list'));
        $this->assertEquals('a', $this->business->lpop('list'));
        $this->assertNull($this->business->lpop('list'));
    }

    public function testPushX()
    {
        $this->assertEquals(0, $this->business->lpushx('list', 'a'));
        $this->assertEquals(0, $this->business->rpushx('list', 'b'));
        $this->assertFalse($this->storage->hasKey('list'));

        $this->assertEquals(1, $this->business->lpush('list', 'c'));

        $this->assertEquals(2, $this->business->lpushx('list', 'd'));
        $this->assertEquals(3, $this->business->rpushx('list', 'e'));

        $this->assertEquals('d', $this->business->lpop('list'));
        $this->assertEquals('c', $this->business->lpop('list'));
        $this->assertEquals('e', $this->business->lpop('list'));
    }

    public function testRpopLpush()
    {
        $this->assertNull($this->business->rpoplpush('a', 'b'));
        $this->assertFalse($this->storage->hasKey('b'));

        $this->assertEquals(3, $this->business->rpush('a', '1', '2', '3'));

        $this->assertEquals('3', $this->business->rpoplpush('a', 'b'));
        $this->assertTrue($this->storage->hasKey('b'));

        $this->assertEquals('2', $this->business->rpoplpush('a', 'b'));
        $this->assertEquals('1', $this->business->rpoplpush('a', 'b'));

        $this->assertFalse($this->storage->hasKey('a'));
    }

    public function testLindex()
    {
        $this->assertNull($this->business->lindex('list', 1));

        $this->business->rpush('list', 'a', 'b', 'c');

        $this->assertEquals('a', $this->business->lindex('list', 0));
        $this->assertEquals('c', $this->business->lindex('list', 2));
        $this->assertEquals('c', $this->business->lindex('list', -1));
        $this->assertEquals('a', $this->business->lindex('list', -3));

        $this->assertNull($this->business->lindex('list', 3));
        $this->assertNull($this->business->lindex('list', -4));
    }

    public function testLrange()
    {
        $this->assertEquals(array(), $this->business->lrange('list', '0', '100'));

        $this->business->rpush('list', 'a', 'b', 'c', 'd');

        $this->assertEquals(array('b', 'c'), $this->business->lrange('list', '1', '2'));
        $this->assertEquals(array('c', 'd'), $this->business->lrange('list', '2', '100'));
        $this->assertEquals(array('a'), $this->business->lrange('list', '0', '0'));
        $this->assertEquals(array('d'), $this->business->lrange('list', '-1', '-1'));

        $this->assertEquals(array(), $this->business->lrange('list', '2', '1'));
        $this->assertEquals(array(), $this->business->lrange('list', '100', '200'));
    }
}
