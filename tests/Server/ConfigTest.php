<?php

use Clue\Redis\Server\Config;

class ConfigTest extends TestCase
{
    private $config;

    public function setUp()
    {
        $this->config = new Config();
    }

    public function testA()
    {
        $this->assertEquals('', $this->config->get('requirepass'));
        $this->config->set('requirepass', 'secret');
        $this->assertEquals('secret', $this->config->get('requirepass'));
    }

    /**
     * @expectedException Exception
     */
    public function testNewKeysAreInvalid()
    {
        $this->config->set('unknown', 'value');
    }
}
