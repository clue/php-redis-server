<?php

use Clue\Redis\Server\Business;
use Clue\Redis\Server\Storage;

class BusinessTest extends TestCase
{
    private $business;
    private $storage;

    public function setUp()
    {
        $this->storage = new Storage();
        $this->business = new Business($this->storage);
    }

    public function testPing()
    {
        $this->assertEquals('PONG', $this->business->ping());
    }

    public function testKeys()
    {
        $this->assertEquals(array(), $this->business->keys('*'));
        $this->assertNull($this->business->randomkey());

        $this->assertTrue($this->business->mset('one', '1', 'two', '2', 'three', '3'));

        $this->assertEquals(array('one', 'two', 'three'), $this->business->keys('*'));
        $this->assertEquals(array('one', 'three'), $this->business->keys('*e'));
        $this->assertEquals(array('one', 'two'), $this->business->keys('*o*'));
        $this->assertEquals(array('one'), $this->business->keys('[eio]*'));
        $this->assertEquals(array(), $this->business->keys('T*'));

        $this->assertEquals(array(), $this->business->keys('[*{?\\'));
    }

    public function testRandomkey()
    {
        $this->assertNull($this->business->randomkey());

        $this->assertTrue($this->business->set('key', 'value'));

        // add a key that expires immediately, effectively leaving only a single 'key' for random selection
        $this->assertTrue($this->business->setex('expired', '0', 'value'));

        $this->assertEquals('key', $this->business->randomkey());
    }

    public function testSort()
    {
        $this->assertEquals(array(), $this->business->sort('list'));

        $this->assertEquals(4, $this->business->rpush('list', '0', '8', '4', '12'));

        $this->assertEquals(array('0', '4', '8', '12'), $this->business->sort('list'));
        $this->assertEquals(array('12', '8', '4', '0'), $this->business->sort('list', 'DESC'));
        $this->assertEquals(array('0', '12', '4', '8'), $this->business->sort('list', 'ALPHA'));
    }

    public function testSortWords()
    {
        $this->assertEquals(4, $this->business->rpush('list', 'dd', 'aa', 'cc', 'bb'));

        $this->assertEquals(array('aa', 'bb', 'cc', 'dd'), $this->business->sort('list', 'ALPHA'));

        $this->setExpectedException('Exception');
        $this->business->sort('list');
    }

    public function testSortStore()
    {
        $this->assertEquals(0, $this->business->sort('list', 'STORE', 'target'));
        $this->assertFalse($this->business->exists('target'));

        $this->assertEquals(4, $this->business->rpush('list', '0', '8', '4', '12'));
        $this->assertEquals(4, $this->business->sort('list', 'STORE', 'target'));

        $this->assertEquals('0', $this->business->lpop('target'));
        $this->assertEquals('4', $this->business->lpop('target'));
        $this->assertEquals('8', $this->business->lpop('target'));
        $this->assertEquals('12', $this->business->lpop('target'));
        $this->assertNull($this->business->lpop('target'));
    }

    public function testSortByLookup()
    {
        $this->assertEquals(4, $this->business->rpush('list', 'three', 'one', 'four', 'two'));
        $this->assertTrue($this->business->mset('weight_three', '3', 'weight_one', '1', 'weight_four', '4', 'weight_two', '2'));

        $this->assertEquals(array('one', 'two', 'three', 'four'), $this->business->sort('list', 'BY', 'weight_*'));
    }

    public function testSortByLookupUnknown()
    {
        $this->assertEquals(4, $this->business->rpush('list', 'three', 'one', 'four', 'two'));
        $this->assertEquals(array('four', 'one', 'three', 'two'), $this->business->sort('list', 'BY', 'unknown_*'));
    }

    public function testSortByLookupNosort()
    {
        $this->assertEquals(4, $this->business->rpush('list', 'three', 'one', 'four', 'two'));
        $this->assertEquals(array('three', 'one', 'four', 'two'), $this->business->sort('list', 'BY', 'nosort'));
    }

    public function testSortGetLookup()
    {
        $this->assertEquals(3, $this->business->rpush('list', '3', '1', '2'));
        $this->assertTrue($this->business->mset('name_1', 'one', 'name_2', 'two', 'name_3', 'three'));

        $this->assertEquals(array('1', '2', '3'), $this->business->sort('list', 'GET', '#'));
        $this->assertEquals(array('one', 'two', 'three'), $this->business->sort('list', 'GET', 'name_*'));
        $this->assertEquals(array('one', '1', null, 'two', '2', null, 'three', '3', null), $this->business->sort('list', 'GET', 'name_*', 'GET', '#', 'GET', 'unknown'));
    }

    public function testSortLimit()
    {
        $this->assertEquals(4, $this->business->rpush('list', '3', '1', '2', '4'));

        $this->assertEquals(array('1', '2'), $this->business->sort('list', 'LIMIT', '0', '2'));
        $this->assertEquals(array('3', '4'), $this->business->sort('list', 'LIMIT', '2', '2'));

        $this->assertEquals(array('3', '4'), $this->business->sort('list', 'LIMIT', '2', '100'));
        $this->assertEquals(array(), $this->business->sort('list', 'LIMIT', '100', '100'));
    }

    public function testSortGetWinsOverLimit()
    {
        $this->assertEquals(3, $this->business->rpush('list', '3', '1', '2'));

        $this->assertEquals(array('1', '1', '2', '2'), $this->business->sort('list', 'GET', '#', 'GET', '#', 'LIMIT', '0', '2'));
    }

    public function testStorage()
    {
        $this->assertFalse($this->business->exists('test'));
        $this->assertTrue($this->business->set('test', 'value'));
        $this->assertTrue($this->business->exists('test'));
        $this->assertEquals('value', $this->business->get('test'));
    }

    public function testSetNx()
    {
        $this->assertEquals(1, $this->business->setnx('test', 'value1'));
        $this->assertEquals(0, $this->business->setnx('test', 'value2'));
        $this->assertEquals('value1', $this->business->get('test'));
    }

    public function testStorageParams()
    {
        $this->assertNull($this->business->set('test', 'value', 'xx'));
        $this->assertNull($this->business->get('test'));

        $this->assertTrue($this->business->set('test', 'value', 'nx'));
        $this->assertEquals('value', $this->business->get('test'));

        $this->assertNull($this->business->set('test', 'newvalue', 'nx'));
        $this->assertEquals('value', $this->business->get('test'));

        $this->assertTrue($this->business->set('test', 'newvalue', 'xx'));
        $this->assertEquals('newvalue', $this->business->get('test'));
    }

    public function testIncrement()
    {
        $this->assertEquals(1, $this->business->incr('counter'));
        $this->assertEquals(2, $this->business->incr('counter'));

        $this->assertEquals(12, $this->business->incrby('counter', 10));

        $this->assertEquals(11, $this->business->decr('counter'));

        $this->assertEquals(9, $this->business->decrby('counter', 2));
    }

    /**
     *
     * @expectedException Clue\Redis\Server\InvalidDatatypeException
     */
    public function testIncrementInvalid()
    {
        $this->business->set('a', 'hello');
        $this->business->incr('a');
    }

    public function testMultiGetSet()
    {
        $this->assertEquals(array(null, null), $this->business->mget('a', 'b'));

        $this->assertTrue($this->business->mset('a', 'value1', 'c', 'value2'));

        $this->assertEquals(array('value1', null, 'value2'), $this->business->mget('a', 'b', 'c'));
    }

    public function testMsetNx()
    {
        $this->assertTrue($this->business->msetnx('a', 'b', 'c', 'd'));
        $this->assertEquals(array('b', 'd'), $this->business->mget('a', 'c'));

        $this->assertFalse($this->business->msetnx('b', 'c', 'c', 'e'));
    }

    /**
     * @expectedException Exception
     */
    public function testMsetInvalidNumberOfArguments()
    {
        $this->business->mset('a', 'b', 'c');
    }

    public function testStrlen()
    {
        $this->assertEquals(0, $this->business->strlen('key'));

        $this->business->set('key', 'value');

        $this->assertEquals(5, $this->business->strlen('key'));
    }

    public function testDel()
    {
        $this->assertEquals(0, $this->business->del('a', 'b', 'c'));

        $this->business->set('a', 'a');
        $this->business->set('c', 'c');

        $this->assertEquals(2, $this->business->del('a', 'b', 'c', 'd'));
    }

    public function testList()
    {
        $this->assertEquals(0, $this->business->llen('list'));

        $this->assertEquals(1, $this->business->rpush('list', 'b'));
        $this->assertEquals(2, $this->business->rpush('list', 'c'));
        $this->assertEquals(3, $this->business->lpush('list', 'a'));

        $this->assertEquals(3, $this->business->llen('list'));
        $this->assertEquals('list', $this->business->type('list'));

        $this->assertEquals('c', $this->business->rpop('list'));
        $this->assertEquals('a', $this->business->lpop('list'));
        $this->assertEquals('b', $this->business->lpop('list'));

        $this->assertNull($this->business->lpop('list'));

        $this->assertFalse($this->business->exists('list'));

        $this->assertEquals(1, $this->business->rpush('list', 'a'));
        $this->assertEquals('a', $this->business->rpop('list'));
        $this->assertEquals(null, $this->business->rpop('list'));

        $this->assertFalse($this->business->exists('list'));
        $this->assertEquals('none', $this->business->type('list'));
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
        $this->assertFalse($this->business->exists('list'));

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
        $this->assertFalse($this->business->exists('b'));

        $this->assertEquals(3, $this->business->rpush('a', '1', '2', '3'));

        $this->assertEquals('3', $this->business->rpoplpush('a', 'b'));
        $this->assertTrue($this->business->exists('b'));

        $this->assertEquals('2', $this->business->rpoplpush('a', 'b'));
        $this->assertEquals('1', $this->business->rpoplpush('a', 'b'));

        $this->assertFalse($this->business->exists('a'));
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

    public function testAppend()
    {
        $this->assertEquals(5, $this->business->append('test', 'value'));
        $this->assertEquals('value', $this->business->get('test'));

        $this->assertEquals(8, $this->business->append('test', '123'));
        $this->assertEquals('value123', $this->business->get('test'));
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testAppendListFails()
    {
        $this->assertEquals(1, $this->business->lpush('list', 'value'));

        $this->business->append('list', 'invalid');
    }

    public function testGetrange()
    {
        $this->assertTrue($this->business->set('test', 'This is a string'));

        $this->assertEquals('This', $this->business->getrange('test', 0, 3));
        $this->assertEquals('ing', $this->business->getrange('test', -3, -1));
        $this->assertEquals('This is a string', $this->business->getrange('test', 0, -1));
        $this->assertEquals('string', $this->business->getrange('test', 10, 100));
        $this->assertEquals('', $this->business->getrange('test', 100, 200));

        $this->assertEquals('', $this->business->getrange('unknown', 0, 3));
    }

    public function testSetrange()
    {
        $this->assertEquals(11, $this->business->setrange('test', 6, 'world'));
        $this->assertEquals("\0\0\0\0\0\0world", $this->business->get('test'));

        $this->assertEquals(11, $this->business->setrange('test', 0, 'hello'));
        $this->assertEquals("hello\0world", $this->business->get('test'));

        $this->assertEquals(12, $this->business->setrange('test', 5, ' world!'));
        $this->assertEquals("hello world!", $this->business->get('test'));
    }

    public function testGetset()
    {
        $this->assertEquals(null, $this->business->getset('test', 'a'));
        $this->assertEquals('a', $this->business->getset('test', 'b'));
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
            array('incrby', 'key', 'invalid'),
            array('decrby', 'key', 'invalid'),
            array('set', 'key', 'value', 'EX', 'invalid'),
            array('setex', 'key', 'invalid', 'value'),
            array('psetex', 'key', 'invalid', 'value'),
            array('expire', 'key', 'invalid'),
            array('expireat', 'key', 'invalid'),
            array('pexpire', 'key', 'invalid'),
            array('pexpireat', 'key', 'invalid'),
        );
    }
}
