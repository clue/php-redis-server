<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Tests\Server\Business;

use Clue\Redis\Server\Business\Strings;
use Clue\Redis\Server\InvalidDatatypeException;
use Clue\Redis\Server\Storage;
use Clue\Redis\Server\Tests\TestCase;

class StringsTest extends TestCase
{
    private $business;

    private $storage;

    public function setUp(): void
    {
        $this->storage = new Storage();
        $this->business = new Strings($this->storage);
    }

    public function testStorage(): void
    {
        static::assertTrue($this->business->set('test', 'value'));
        static::assertTrue($this->storage->hasKey('test'));
        static::assertEquals('value', $this->business->get('test'));
    }

    public function testSetNx(): void
    {
        static::assertEquals(1, $this->business->setnx('test', 'value1'));
        static::assertEquals(0, $this->business->setnx('test', 'value2'));
        static::assertEquals('value1', $this->business->get('test'));
    }

    public function testStorageParams(): void
    {
        static::assertNull($this->business->set('test', 'value', 'xx'));
        static::assertNull($this->business->get('test'));

        static::assertTrue($this->business->set('test', 'value', 'nx'));
        static::assertEquals('value', $this->business->get('test'));

        static::assertNull($this->business->set('test', 'newvalue', 'nx'));
        static::assertEquals('value', $this->business->get('test'));

        static::assertTrue($this->business->set('test', 'newvalue', 'xx'));
        static::assertEquals('newvalue', $this->business->get('test'));
    }

    public function testIncrement(): void
    {
        static::assertEquals(1, $this->business->incr('counter'));
        static::assertEquals(2, $this->business->incr('counter'));

        static::assertEquals(12, $this->business->incrby('counter', 10));

        static::assertEquals(11, $this->business->decr('counter'));

        static::assertEquals(9, $this->business->decrby('counter', 2));
    }

    public function testIncrementInvalid(): void
    {
        $this->expectException(InvalidDatatypeException::class);
        $this->business->set('a', 'hello');
        $this->business->incr('a');
    }

    public function testMultiGetSet(): void
    {
        static::assertEquals([null, null], $this->business->mget('a', 'b'));

        static::assertTrue($this->business->mset('a', 'value1', 'c', 'value2'));

        static::assertEquals(['value1', null, 'value2'], $this->business->mget('a', 'b', 'c'));
    }

    public function testMsetNx(): void
    {
        static::assertTrue($this->business->msetnx('a', 'b', 'c', 'd'));
        static::assertEquals(['b', 'd'], $this->business->mget('a', 'c'));

        static::assertFalse($this->business->msetnx('b', 'c', 'c', 'e'));
    }

    public function testMsetInvalidNumberOfArguments(): void
    {
        $this->expectException(\Exception::class);
        $this->business->mset('a', 'b', 'c');
    }

    public function testStrlen(): void
    {
        static::assertEquals(0, $this->business->strlen('key'));

        $this->business->set('key', 'value');

        static::assertEquals(5, $this->business->strlen('key'));
    }

    public function testAppend(): void
    {
        static::assertEquals(5, $this->business->append('test', 'value'));
        static::assertEquals('value', $this->business->get('test'));

        static::assertEquals(8, $this->business->append('test', '123'));
        static::assertEquals('value123', $this->business->get('test'));
    }

    public function testAppendListFails(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $list = $this->storage->getOrCreateList('list');
        $list->push('value');

        $this->business->append('list', 'invalid');
    }

    public function testGetrange(): void
    {
        static::assertTrue($this->business->set('test', 'This is a string'));

        static::assertEquals('This', $this->business->getrange('test', 0, 3));
        static::assertEquals('ing', $this->business->getrange('test', -3, -1));
        static::assertEquals('This is a string', $this->business->getrange('test', 0, -1));
        static::assertEquals('string', $this->business->getrange('test', 10, 100));
        static::assertEquals('', $this->business->getrange('test', 100, 200));

        static::assertEquals('', $this->business->getrange('unknown', 0, 3));
    }

    public function testSetrange(): void
    {
        static::assertEquals(11, $this->business->setrange('test', 6, 'world'));
        static::assertEquals("\0\0\0\0\0\0world", $this->business->get('test'));

        static::assertEquals(11, $this->business->setrange('test', 0, 'hello'));
        static::assertEquals("hello\0world", $this->business->get('test'));

        static::assertEquals(12, $this->business->setrange('test', 5, ' world!'));
        static::assertEquals('hello world!', $this->business->get('test'));
    }

    public function testGetset(): void
    {
        static::assertNull($this->business->getset('test', 'a'));
        static::assertEquals('a', $this->business->getset('test', 'b'));
    }

    /**
     * @dataProvider provideInvalidIntegerArgument
     */
    public function testInvalidIntegerArgument($method, $arg0): void
    {
        if ($method === 'set') {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('ERR value is not an integer or out of range');
        } else {
            $this->expectException(\TypeError::class);
            $this->expectExceptionMessage("Argument 2 passed to Clue\Redis\Server\Business\Strings::$method() must be of the type int, string given");
        }
        $args = func_get_args();
        unset($args[0]);

        call_user_func_array([$this->business, $method], $args);
    }

    public function provideInvalidIntegerArgument()
    {
        return [
            ['incrby', 'key', 'invalid'],
            ['decrby', 'key', 'invalid'],
            ['set', 'key', 'value', 'EX', 'invalid'],
            ['setex', 'key', 'invalid', 'value'],
            ['psetex', 'key', 'invalid', 'value'],
        ];
    }
}
