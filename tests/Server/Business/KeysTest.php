<?php

use Clue\Redis\Server\Storage;
use Clue\Redis\Server\Business\Keys;

class KeysTest extends TestCase
{
    private $business;
    private $storage;

    public function setUp()
    {
        $this->storage = new Storage();
        $this->business = new Keys($this->storage);
    }

    public function testKeys()
    {
        $this->assertEquals(array(), $this->business->keys('*'));
        $this->assertNull($this->business->randomkey());

        $this->storage->setString('one', 1);
        $this->storage->setString('two', 2);
        $this->storage->setString('three', 3);

        $this->assertEquals(array('one', 'two', 'three'), $this->business->keys('*'));
        $this->assertEquals(array('one', 'three'), $this->business->keys('*e'));
        $this->assertEquals(array('one', 'two'), $this->business->keys('*o*'));
        $this->assertEquals(array('one'), $this->business->keys('[eio]*'));
        $this->assertEquals(array(), $this->business->keys('T*'));

        $this->assertEquals(array(), $this->business->keys('[*{?\\'));
    }

    public function testExpiry()
    {
        $this->assertEquals(-2, $this->business->ttl('key'));
        $this->assertEquals(-2, $this->business->pttl('key'));

        $this->assertFalse($this->business->pexpire('key', '1000'));
        $this->assertFalse($this->business->persist('key'));

        $this->storage->setString('key', 'value');

        $this->assertEquals(-1, $this->business->ttl('key'));
        $this->assertEquals(-1, $this->business->pttl('key'));
        $this->assertFalse($this->business->persist('key'));

        $this->assertTrue($this->business->expire('key', '10'));

        $this->assertLessThan(10, $this->business->ttl('key'));
        $this->assertLessThan(10000, $this->business->pttl('key'));

        $this->assertTrue($this->business->persist('key'));
        $this->assertEquals(-1, $this->business->ttl('key'));
    }

    public function testRename()
    {
        $this->storage->setString('key', 'value');

        $this->assertTrue($this->business->rename('key', 'new'));

        $this->assertTrue($this->business->exists('new'));
        $this->assertFalse($this->business->exists('key'));
    }

    /**
     * @expectedException Exception
     */
    public function testRenameNonExistant()
    {
        $this->business->rename('invalid', 'new');
    }

    /**
     * @expectedException Exception
     */
    public function testRenameSelf()
    {
        $this->storage->setString('key', 'value');
        $this->business->rename('key', 'key');
    }

    public function testRenameOverwrite()
    {
        $this->storage->setString('a', 'a');

        $this->storage->setString('target', 'b');
        $this->storage->setTimeout('target', microtime(true) + 10);

        $this->assertTrue($this->business->rename('a', 'target'));
        $this->assertTrue($this->business->exists('target'));
        $this->assertFalse($this->business->exists('a'));

        $this->assertEquals(-1, $this->business->ttl('target'));
    }

    public function testRenameNx()
    {
        $this->storage->setString('a', 'a');

        $this->assertTrue($this->business->renamenx('a', 'b'));

        $this->storage->setString('c', 'c');

        $this->assertFalse($this->business->renamenx('b', 'c'));
    }

    public function testType()
    {
        $this->assertEquals('none', $this->business->type('nothing'));

        $this->storage->setString('key', 'value');
        $this->assertEquals('string', $this->business->type('key'));

        $list = $this->storage->getOrCreateList('list');
        $list->push('value');
        $this->assertEquals('list', $this->business->type('list'));
    }

    public function testRandomkey()
    {
        $this->assertNull($this->business->randomkey());

        $this->storage->setString('key', 'value');

        $this->assertEquals('key', $this->business->randomkey());
    }

    public function testSort()
    {
        $this->assertEquals(array(), $this->business->sort('list'));

        $list = $this->storage->getOrCreateList('list');
        $list->push('0');
        $list->push('8');
        $list->push('4');
        $list->push('12');

        $this->assertEquals(array('0', '4', '8', '12'), $this->business->sort('list'));
        $this->assertEquals(array('12', '8', '4', '0'), $this->business->sort('list', 'DESC'));
        $this->assertEquals(array('0', '12', '4', '8'), $this->business->sort('list', 'ALPHA'));
    }

    public function testSortWords()
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('dd');
        $list->push('aa');
        $list->push('cc');
        $list->push('bb');

        $this->assertEquals(array('aa', 'bb', 'cc', 'dd'), $this->business->sort('list', 'ALPHA'));

        $this->setExpectedException('Exception');
        $this->business->sort('list');
    }

    public function testSortStore()
    {
        $this->assertEquals(0, $this->business->sort('list', 'STORE', 'target'));
        $this->assertFalse($this->storage->hasKey('target'));

        $list = $this->storage->getOrCreateList('list');
        $list->push('0');
        $list->push('8');
        $list->push('4');
        $list->push('12');

        $this->assertEquals(4, $this->business->sort('list', 'STORE', 'target'));

        $target = $this->storage->getOrCreateList('target');

        $this->assertEquals('0', $target->shift());
        $this->assertEquals('4', $target->shift());
        $this->assertEquals('8', $target->shift());
        $this->assertEquals('12', $target->shift());
        $this->assertTrue($target->isEmpty());
    }

    public function testSortByLookup()
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('three');
        $list->push('one');
        $list->push('four');
        $list->push('two');

        $this->storage->setString('weight_three', 3);
        $this->storage->setString('weight_one', 1);
        $this->storage->setString('weight_four', 4);
        $this->storage->setString('weight_two', 2);

        $this->assertEquals(array('one', 'two', 'three', 'four'), $this->business->sort('list', 'BY', 'weight_*'));
    }

    public function testSortByLookupUnknown()
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('three');
        $list->push('one');
        $list->push('four');
        $list->push('two');

        $this->assertEquals(array('four', 'one', 'three', 'two'), $this->business->sort('list', 'BY', 'unknown_*'));
    }

    public function testSortByLookupNosort()
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('three');
        $list->push('one');
        $list->push('four');
        $list->push('two');

        $this->assertEquals(array('three', 'one', 'four', 'two'), $this->business->sort('list', 'BY', 'nosort'));
    }

    public function testSortGetLookup()
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('3');
        $list->push('1');
        $list->push('2');

        $this->storage->setString('name_1', 'one');
        $this->storage->setString('name_2', 'two');
        $this->storage->setString('name_3', 'three');

        $this->assertEquals(array('1', '2', '3'), $this->business->sort('list', 'GET', '#'));
        $this->assertEquals(array('one', 'two', 'three'), $this->business->sort('list', 'GET', 'name_*'));
        $this->assertEquals(array('one', '1', null, 'two', '2', null, 'three', '3', null), $this->business->sort('list', 'GET', 'name_*', 'GET', '#', 'GET', 'unknown'));
    }

    public function testSortLimit()
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('3');
        $list->push('1');
        $list->push('2');
        $list->push('4');

        $this->assertEquals(array('1', '2'), $this->business->sort('list', 'LIMIT', '0', '2'));
        $this->assertEquals(array('3', '4'), $this->business->sort('list', 'LIMIT', '2', '2'));

        $this->assertEquals(array('3', '4'), $this->business->sort('list', 'LIMIT', '2', '100'));
        $this->assertEquals(array(), $this->business->sort('list', 'LIMIT', '100', '100'));
    }

    public function testSortGetWinsOverLimit()
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('3');
        $list->push('1');
        $list->push('2');

        $this->assertEquals(array('1', '1', '2', '2'), $this->business->sort('list', 'GET', '#', 'GET', '#', 'LIMIT', '0', '2'));
    }

    public function testStorage()
    {
        $this->assertFalse($this->business->exists('test'));

        $this->storage->setString('test', 'value');

        $this->assertTrue($this->business->exists('test'));
    }

    public function testDel()
    {
        $this->assertEquals(0, $this->business->del('a', 'b', 'c'));

        $this->storage->setString('a', 'a');
        $this->storage->setString('c', 'c');

        $this->assertEquals(2, $this->business->del('a', 'b', 'c', 'd'));
    }

    /**
     * @expectedException Exception
     * @expectedExceptionMessage ERR value is not an integer or out of range
     * @dataProvider provideInvalidIntegerArgument
     */
    public function testInvalidIntegerArgument($method, $arg0)
    {
        $args = func_get_args();
        unset($args[0]);

        call_user_func_array(array($this->business, $method), $args);
    }

    public function provideInvalidIntegerArgument()
    {
        return array(
            array('expire', 'key', 'invalid'),
            array('expireat', 'key', 'invalid'),
            array('pexpire', 'key', 'invalid'),
            array('pexpireat', 'key', 'invalid'),
        );
    }
}
