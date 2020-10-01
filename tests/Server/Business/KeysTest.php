<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Tests\Server\Business;

use Clue\Redis\Server\Business\Keys;
use Clue\Redis\Server\Storage;
use Clue\Redis\Server\Tests\TestCase;

class KeysTest extends TestCase
{
    private $business;

    private $storage;

    public function setUp(): void
    {
        $this->storage = new Storage();
        $this->business = new Keys($this->storage);
    }

    public function testKeys(): void
    {
        static::assertEquals([], $this->business->keys('*'));
        static::assertNull($this->business->randomkey());

        $this->storage->setString('one', 1);
        $this->storage->setString('two', 2);
        $this->storage->setString('three', 3);

        static::assertEquals(['one', 'two', 'three'], $this->business->keys('*'));
        static::assertEquals(['one', 'three'], $this->business->keys('*e'));
        static::assertEquals(['one', 'two'], $this->business->keys('*o*'));
        static::assertEquals(['one'], $this->business->keys('[eio]*'));
        static::assertEquals([], $this->business->keys('T*'));

        static::assertEquals([], $this->business->keys('[*{?\\'));
    }

    public function testExpiry(): void
    {
        static::assertEquals(-2, $this->business->ttl('key'));
        static::assertEquals(-2, $this->business->pttl('key'));

        static::assertFalse($this->business->pexpire('key', 1_000));
        static::assertFalse($this->business->persist('key'));

        $this->storage->setString('key', 'value');

        static::assertEquals(-1, $this->business->ttl('key'));
        static::assertEquals(-1, $this->business->pttl('key'));
        static::assertFalse($this->business->persist('key'));

        static::assertTrue($this->business->expire('key', 10));

        static::assertLessThan(10, $this->business->ttl('key'));
        static::assertLessThan(10000, $this->business->pttl('key'));

        static::assertTrue($this->business->persist('key'));
        static::assertEquals(-1, $this->business->ttl('key'));
    }

    public function testRename(): void
    {
        $this->storage->setString('key', 'value');

        static::assertTrue($this->business->rename('key', 'new'));

        static::assertTrue($this->business->exists('new'));
        static::assertFalse($this->business->exists('key'));
    }

    public function testRenameNonExistant(): void
    {
        $this->expectException(\Exception::class);
        $this->business->rename('invalid', 'new');
    }

    public function testRenameSelf(): void
    {
        $this->expectException(\Exception::class);
        $this->storage->setString('key', 'value');
        $this->business->rename('key', 'key');
    }

    public function testRenameOverwrite(): void
    {
        $this->storage->setString('a', 'a');

        $this->storage->setString('target', 'b');
        $this->storage->setTimeout('target', (int) microtime(true) + 10);

        static::assertTrue($this->business->rename('a', 'target'));
        static::assertTrue($this->business->exists('target'));
        static::assertFalse($this->business->exists('a'));

        static::assertEquals(-1, $this->business->ttl('target'));
    }

    public function testRenameNx(): void
    {
        $this->storage->setString('a', 'a');

        static::assertTrue($this->business->renamenx('a', 'b'));

        $this->storage->setString('c', 'c');

        static::assertFalse($this->business->renamenx('b', 'c'));
    }

    public function testType(): void
    {
        static::assertEquals('none', $this->business->type('nothing'));

        $this->storage->setString('key', 'value');
        static::assertEquals('string', $this->business->type('key'));

        $list = $this->storage->getOrCreateList('list');
        $list->push('value');
        static::assertEquals('list', $this->business->type('list'));
    }

    public function testRandomkey(): void
    {
        static::assertNull($this->business->randomkey());

        $this->storage->setString('key', 'value');

        static::assertEquals('key', $this->business->randomkey());
    }

    public function testSort(): void
    {
        static::assertEquals([], $this->business->sort('list'));

        $list = $this->storage->getOrCreateList('list');
        $list->push('0');
        $list->push('8');
        $list->push('4');
        $list->push('12');

        static::assertEquals(['0', '4', '8', '12'], $this->business->sort('list'));
        static::assertEquals(['12', '8', '4', '0'], $this->business->sort('list', 'DESC'));
        static::assertEquals(['0', '12', '4', '8'], $this->business->sort('list', 'ALPHA'));
    }

    public function testSortWords(): void
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('dd');
        $list->push('aa');
        $list->push('cc');
        $list->push('bb');

        static::assertEquals(['aa', 'bb', 'cc', 'dd'], $this->business->sort('list', 'ALPHA'));

        $this->expectException(\Exception::class);
        $this->business->sort('list');
    }

    public function testSortStore(): void
    {
        static::assertEquals(0, $this->business->sort('list', 'STORE', 'target'));
        static::assertFalse($this->storage->hasKey('target'));

        $list = $this->storage->getOrCreateList('list');
        $list->push('0');
        $list->push('8');
        $list->push('4');
        $list->push('12');

        static::assertEquals(4, $this->business->sort('list', 'STORE', 'target'));

        $target = $this->storage->getOrCreateList('target');

        static::assertEquals('0', $target->shift());
        static::assertEquals('4', $target->shift());
        static::assertEquals('8', $target->shift());
        static::assertEquals('12', $target->shift());
        static::assertTrue($target->isEmpty());
    }

    public function testSortByLookup(): void
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

        static::assertEquals(['one', 'two', 'three', 'four'], $this->business->sort('list', 'BY', 'weight_*'));
    }

    public function testSortByLookupUnknown(): void
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('three');
        $list->push('one');
        $list->push('four');
        $list->push('two');

        static::assertEquals(['four', 'one', 'three', 'two'], $this->business->sort('list', 'BY', 'unknown_*'));
    }

    public function testSortByLookupNosort(): void
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('three');
        $list->push('one');
        $list->push('four');
        $list->push('two');

        static::assertEquals(['three', 'one', 'four', 'two'], $this->business->sort('list', 'BY', 'nosort'));
    }

    public function testSortGetLookup(): void
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('3');
        $list->push('1');
        $list->push('2');

        $this->storage->setString('name_1', 'one');
        $this->storage->setString('name_2', 'two');
        $this->storage->setString('name_3', 'three');

        static::assertEquals(['1', '2', '3'], $this->business->sort('list', 'GET', '#'));
        static::assertEquals(['one', 'two', 'three'], $this->business->sort('list', 'GET', 'name_*'));
        static::assertEquals(['one', '1', null, 'two', '2', null, 'three', '3', null], $this->business->sort('list', 'GET', 'name_*', 'GET', '#', 'GET', 'unknown'));
    }

    public function testSortLimit(): void
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('3');
        $list->push('1');
        $list->push('2');
        $list->push('4');

        static::assertEquals(['1', '2'], $this->business->sort('list', 'LIMIT', '0', '2'));
        static::assertEquals(['3', '4'], $this->business->sort('list', 'LIMIT', '2', '2'));

        static::assertEquals(['3', '4'], $this->business->sort('list', 'LIMIT', '2', '100'));
        static::assertEquals([], $this->business->sort('list', 'LIMIT', '100', '100'));
    }

    public function testSortGetWinsOverLimit(): void
    {
        $list = $this->storage->getOrCreateList('list');
        $list->push('3');
        $list->push('1');
        $list->push('2');

        static::assertEquals(['1', '1', '2', '2'], $this->business->sort('list', 'GET', '#', 'GET', '#', 'LIMIT', '0', '2'));
    }

    public function testStorage(): void
    {
        static::assertFalse($this->business->exists('test'));

        $this->storage->setString('test', 'value');

        static::assertTrue($this->business->exists('test'));
    }

    public function testDel(): void
    {
        static::assertEquals(0, $this->business->del('a', 'b', 'c'));

        $this->storage->setString('a', 'a');
        $this->storage->setString('c', 'c');

        static::assertEquals(2, $this->business->del('a', 'b', 'c', 'd'));
    }

    /**
     * @dataProvider provideInvalidIntegerArgument
     */
    public function testInvalidIntegerArgument($method, $arg0): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("Argument 2 passed to Clue\Redis\Server\Business\Keys::$method() must be of the type int, string given");
        $args = func_get_args();
        unset($args[0]);

        call_user_func_array([$this->business, $method], $args);
    }

    public function provideInvalidIntegerArgument()
    {
        return [
            ['expire', 'key', 'invalid'],
            ['expireat', 'key', 'invalid'],
            ['pexpire', 'key', 'invalid'],
            ['pexpireat', 'key', 'invalid'],
        ];
    }
}
