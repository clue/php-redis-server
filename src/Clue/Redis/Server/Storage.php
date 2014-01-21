<?php

namespace Clue\Redis\Server;

class Storage
{
    private $storage = array();
    private $timeout = array();

    public function unsetKey($key)
    {
        unset($this->storage[$key], $this->timeout[$key]);
    }

    public function hasKey($key)
    {
        return isset($this->storage[$key]) && (!isset($this->timeout[$key]) || $this->timeout[$key] > microtime(true));
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

    public function getListOrNull($key)
    {
        $value = $this->get($key);

        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidDatatypeException();
        }

        return $value;
    }

    public function& getListRef($key)
    {
        if (!$this->hasKey($key)) {
            $this->storage[$key] = array();
            unset($this->timeout[$key]);
        } elseif (!is_array($this->storage[$key])) {
            throw new InvalidDatatypeException();
        }

        return $this->storage[$key];
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
}
