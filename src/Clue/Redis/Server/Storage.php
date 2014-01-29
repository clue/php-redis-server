<?php

namespace Clue\Redis\Server;

use SplDoublyLinkedList;

class Storage
{
    private $storage = array();
    private $timeout = array();

    private $id;

    public function __construct($id = '0')
    {
        $this->id = $id;
    }

    public function getId()
    {
        return $this->id;
    }

    public function unsetKey($key)
    {
        unset($this->storage[$key], $this->timeout[$key]);
    }

    public function hasKey($key)
    {
        return isset($this->storage[$key]) && (!isset($this->timeout[$key]) || $this->timeout[$key] > microtime(true));
    }

    public function getAllKeys()
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

    public function setString($key, $value)
    {
        $this->storage[$key] = (string)$value;
        unset($this->timeout[$key]);
    }

    public function rename($oldkey, $newkey)
    {
        if ($oldkey != $newkey) {
            $this->storage[$newkey] = $this->storage[$oldkey];
            unset($this->storage[$oldkey]);
            if (isset($this->timeout[$oldkey])) {
                $this->timeout[$newkey] = $this->timeout[$oldkey];
                unset($this->timeout[$oldkey]);
            }
        }
    }

    public function get($key)
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

    public function getOrCreateList($key)
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

    public function getStringOrNull($key)
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

    public function& getStringRef($key)
    {
        if (!$this->hasKey($key)) {
            $this->storage[$key] = '';
            unset($this->timeout[$key]);
        } else if (!is_string($this->storage[$key])) {
            throw new InvalidDatatypeException();
        }

        return $this->storage[$key];
    }

    public function getIntegerOrNull($key)
    {
        $value = $this->getStringOrNull($key);

        if ($value !== null && !is_numeric($value)) {
            throw new InvalidDatatypeException('ERR value is not an integer or out of range');
        }

        return (int)$value;
    }

    public function getTimeout($key)
    {
        return isset($this->timeout[$key]) ? $this->timeout[$key] : null;
    }

    public function setTimeout($key, $timestamp)
    {
        if ($timestamp === null || !isset($this->storage[$key])) {
            unset($this->timeout[$key]);
        } else {
            $this->timeout[$key] = $timestamp;
        }
    }

    private function removeAllExpired()
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
