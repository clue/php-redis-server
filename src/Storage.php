<?php

declare(strict_types=1);

namespace Clue\Redis\Server;

use SplDoublyLinkedList;

class Storage
{
    private array $storage = [];

    private array $timeout = [];

    private string $id;

    public function __construct(string $id = '0')
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function unsetKey(string $key): void
    {
        unset($this->storage[$key], $this->timeout[$key]);
    }

    public function hasKey(string $key): bool
    {
        return isset($this->storage[$key]) && (!isset($this->timeout[$key]) || $this->timeout[$key] > microtime(true));
    }

    public function getAllKeys(): array
    {
        $this->removeAllExpired();

        return array_keys($this->storage);
    }

    public function getRandomKey()
    {
        $this->removeAllExpired();

        if (!$this->storage) {
            return null;
        }

        return array_rand($this->storage);
    }

    public function setString(string $key, $value): void
    {
        $this->storage[$key] = (string) $value;
        unset($this->timeout[$key]);
    }

    public function rename(string $oldkey, string $newkey): void
    {
        if ($oldkey !== $newkey) {
            $this->storage[$newkey] = $this->storage[$oldkey];
            unset($this->storage[$oldkey]);
            if (isset($this->timeout[$oldkey])) {
                $this->timeout[$newkey] = $this->timeout[$oldkey];
                unset($this->timeout[$oldkey]);
            }
        }
    }

    public function get(string $key)
    {
        if (!isset($this->storage[$key])) {
            return null;
        }
        if (isset($this->timeout[$key]) && microtime(true) > $this->timeout[$key]) {
            unset($this->storage[$key], $this->timeout[$key]);

            return null;
        }

        return $this->storage[$key];
    }

    public function getOrCreateList(string $key): SplDoublyLinkedList
    {
        if ($this->hasKey($key)) {
            if (!($this->storage[$key] instanceof SplDoublyLinkedList)) {
                throw new InvalidDatatypeException('WRONGTYPE Operation against a key holding the wrong kind of value');
            }

            return $this->storage[$key];
        }

        unset($this->timeout[$key]);

        return $this->storage[$key] = new SplDoublyLinkedList();
    }

    public function getStringOrNull(string $key): ?string
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new InvalidDatatypeException('WRONGTYPE Operation against a key holding the wrong kind of value');
        }

        return $value;
    }

    public function &getStringRef(string $key)
    {
        if (!$this->hasKey($key)) {
            $this->storage[$key] = '';
            unset($this->timeout[$key]);
        } elseif (!is_string($this->storage[$key])) {
            throw new InvalidDatatypeException();
        }

        return $this->storage[$key];
    }

    public function getIntegerOrNull(string $key): ?int
    {
        $value = $this->getStringOrNull($key);

        if ($value !== null && !is_numeric($value)) {
            throw new InvalidDatatypeException('ERR value is not an integer or out of range');
        }

        return (int) $value;
    }

    public function getTimeout(string $key): ?int
    {
        return isset($this->timeout[$key]) ? $this->timeout[$key] : null;
    }

    public function setTimeout(string $key, ?int $timestamp): void
    {
        if ($timestamp === null || !isset($this->storage[$key])) {
            unset($this->timeout[$key]);
        } else {
            $this->timeout[$key] = $timestamp;
        }
    }

    public function reset(): void
    {
        $this->storage = $this->timeout = [];
    }

    public function count(): int
    {
        $this->removeAllExpired();

        return count($this->storage);
    }

    private function removeAllExpired(): void
    {
        if ($this->timeout) {
            $now = microtime(true);

            foreach ($this->timeout as $key => $ts) {
                if ($ts < $now) {
                    unset($this->storage[$key], $this->timeout[$key]);
                }
            }
        }
    }
}
