<?php

namespace Clue\Redis\Server;

use IteratorAggregate;
use ArrayIterator;
use IteratorIterator;
use InvalidArgumentException;

class Config implements IteratorAggregate
{
    private $config = array(
        'requirepass' => '',
    );

    public function get($key)
    {
        return $this->config[$key];
    }

    public function set($key, $value)
    {
        if (!isset($this->config[$key])) {
            throw new InvalidArgumentException('ERR Unsupported CONFIG parameter: ' . $key);
        }
        $this->config[$key] = (string)$value;
    }

    public function getIterator()
    {
        return new IteratorIterator(new ArrayIterator($this->config));
    }
}
