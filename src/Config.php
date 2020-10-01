<?php

declare(strict_types=1);

namespace Clue\Redis\Server;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use IteratorIterator;

class Config implements IteratorAggregate
{
    private array $config = [
        'requirepass' => '',
    ];

    public function get(string $key): string
    {
        return $this->config[$key];
    }

    public function set(string $key, $value): void
    {
        if (!isset($this->config[$key])) {
            throw new InvalidArgumentException('ERR Unsupported CONFIG parameter: ' . $key);
        }
        $this->config[$key] = (string) $value;
    }

    public function getIterator(): iterable
    {
        return new IteratorIterator(new ArrayIterator($this->config));
    }
}
