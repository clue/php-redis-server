<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Tests\Server;

use Clue\Redis\Server\Config;
use Clue\Redis\Server\Tests\TestCase;

class ConfigTest extends TestCase
{
    private $config;

    public function setUp(): void
    {
        $this->config = new Config();
    }

    public function testA(): void
    {
        static::assertEquals('', $this->config->get('requirepass'));
        $this->config->set('requirepass', 'secret');
        static::assertEquals('secret', $this->config->get('requirepass'));
    }

    public function testNewKeysAreInvalid(): void
    {
        $this->expectException(\Exception::class);
        $this->config->set('unknown', 'value');
    }
}
