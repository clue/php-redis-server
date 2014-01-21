<?php

namespace Clue\Redis\React\Server;

class Storage
{
    private $storage = array();

    public function unsetKey($key)
    {
        unset($this->storage[$key]);
    }

    public function hasKey($key)
    {
        return isset($this->storage[$key]);
    }

    public function setString($key, $value)
    {
        $this->storage[$key] = (string)$value;
    }

    public function rename($oldkey, $newkey)
    {
        if ($oldkey != $newkey) {
            $this->storage[$newkey] = $this->storage[$oldkey];
            unset($this->storage[$oldkey]);
        }
    }

    public function get($key)
    {
        return $this->storage[$key];
    }

    public function getListOrNull($key)
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        if (!is_array($this->storage[$key])) {
            throw new InvalidDatatypeException();
        }

        return $this->storage[$key];
    }

    public function& getListRef($key)
    {
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = array();
        } elseif (!is_array($this->storage[$key])) {
            throw new InvalidDatatypeException();
        }

        return $this->storage[$key];
    }

    public function getStringOrNull($key)
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        if (!is_string($this->storage[$key])) {
            throw new InvalidDatatypeException('WRONGTYPE Operation against a key holding the wrong kind of value');
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
}
