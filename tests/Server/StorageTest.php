<?php

use Clue\Redis\Server\Storage;

class StorageTest extends TestCase
{
    private $storage;

    public function setUp()
    {
        $this->storage = new Storage();
    }

    public function testHasSetUnset()
    {
        $this->assertFalse($this->storage->hasKey('key'));

        $this->storage->setString('key', 'value');
        $this->assertTrue($this->storage->hasKey('key'));

        $this->storage->unsetKey('key');
        $this->assertFalse($this->storage->hasKey('key'));

    }

    public function testString()
    {
        $this->assertEquals(null, $this->storage->getStringOrNull('key'));

        $this->storage->setString('key', 'value');

        $this->assertEquals('value', $this->storage->getStringOrNull('key'));
    }
}
