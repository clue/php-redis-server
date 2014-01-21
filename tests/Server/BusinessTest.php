<?php

use Clue\Redis\React\Server\Business;
use Clue\Redis\React\Server\Storage;
use Clue\Redis\Protocol\Model\StatusReply;

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
        $this->assertEquals(new StatusReply('PONG'), $this->business->ping());
    }

    public function testStorage()
    {
        $this->assertEquals(0, $this->business->exists('test'));
        $this->assertEquals(new StatusReply('OK'), $this->business->set('test', 'value'));
        $this->assertEquals(1, $this->business->exists('test'));
        $this->assertEquals('value', $this->business->get('test'));
    }

    public function testSetNx()
    {
        $this->assertEquals(1, $this->business->setnx('test', 'value1'));
        $this->assertEquals(0, $this->business->setnx('test', 'value2'));
        $this->assertEquals('value1', $this->business->get('test'));
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
     * @expectedException Clue\Redis\React\Server\InvalidDatatypeException
     */
    public function testIncrementInvalid()
    {
        $this->business->set('a', 'hello');
        $this->business->incr('a');
    }

    public function testMultiGetSet()
    {
        $this->assertEquals(array(null, null), $this->business->mget('a', 'b'));

        $this->assertEquals(new StatusReply('OK'), $this->business->mset('a', 'value1', 'c', 'value2'));

        $this->assertEquals(array('value1', null, 'value2'), $this->business->mget('a', 'b', 'c'));
    }

    /**
     * @expectedException Exception
     */
    public function testMsetInvalid()
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
}
