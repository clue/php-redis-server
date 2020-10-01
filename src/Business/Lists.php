<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Client;
use Clue\Redis\Server\Storage;

class Lists
{
    private Storage $storage;

    public function __construct(?Storage $storage = null)
    {
        $this->storage = $storage ?? new Storage();
    }

    public function lpush(string $key, $value): int
    {
        $list = $this->storage->getOrCreateList($key);

        $values = func_get_args();
        unset($values[0]);

        foreach ($values as $value) {
            $list->unshift($value);
        }

        return $list->count();
    }

    public function lpushx(string $key, $value): int
    {
        if (!$this->storage->hasKey($key)) {
            return 0;
        }

        return $this->lpush($key, $value);
    }

    public function rpush(string $key, $value): int
    {
        $list = $this->storage->getOrCreateList($key);

        $values = func_get_args();
        unset($values[0]);

        foreach ($values as $value) {
            $list->push($value);
        }

        return $list->count();
    }

    public function rpushx(string $key, $value): int
    {
        if (!$this->storage->hasKey($key)) {
            return 0;
        }

        return $this->rpush($key, $value);
    }

    public function lpop(string $key)
    {
        if (!$this->storage->hasKey($key)) {
            return null;
        }

        $list = $this->storage->getOrCreateList($key);

        $value = $list->shift();

        if ($list->isEmpty()) {
            $this->storage->unsetKey($key);
        }

        return $value;
    }

    public function rpop(string $key)
    {
        if (!$this->storage->hasKey($key)) {
            return null;
        }

        $list = $this->storage->getOrCreateList($key);

        $value = $list->pop();

        if ($list->isEmpty()) {
            $this->storage->unsetKey($key);
        }

        return $value;
    }

    public function rpoplpush(string $source, string $destination)
    {
        if (!$this->storage->hasKey($source)) {
            return null;
        }
        $sourceList = $this->storage->getOrCreateList($source);
        $destinationList = $this->storage->getOrCreateList($destination);

        $value = $sourceList->pop();
        $destinationList->unshift($value);

        if ($sourceList->isEmpty()) {
            $this->storage->unsetKey($source);
        }

        return $value;
    }

    public function llen(string $key): int
    {
        if (!$this->storage->hasKey($key)) {
            return 0;
        }

        return $this->storage->getOrCreateList($key)->count();
    }

    public function lindex(string $key, int $index)
    {
        $len = $this->llen($key);
        if ($len === 0) {
            return null;
        }

        $list = $this->storage->getOrCreateList($key);

        // LINDEX actually checks the integer *after* checking the list and type

        if ($index < 0) {
            $index += $len;
        }
        if ($index < 0 || $index >= $len) {
            return null;
        }

        return $list->offsetGet($index);
    }

    public function lrange(string $key, int $start, int $stop): array
    {
        if (!$this->storage->hasKey($key)) {
            return [];
        }

        $list = $this->storage->getOrCreateList($key);

        $len = $this->llen($key);
        if ($start < 0) {
            $start += $len;
        }
        if ($stop < 0) {
            $stop += $len;
        }
        if (($stop + 1) > $len) {
            $stop = $len - 1;
        }

        if ($stop < $start || $stop < 0 || $start > $len) {
            return [];
        }

        $list->rewind();
        for ($i = 0; $i < $start; ++$i) {
            $list->next();
        }

        $ret = [];

        while ($i <= $stop) {
            $ret[] = $list->current();
            $list->next();
            ++$i;
        }

        return $ret;
    }

    public function setClient(Client $client): void
    {
        $this->storage = $client->getDatabase();
    }
}
