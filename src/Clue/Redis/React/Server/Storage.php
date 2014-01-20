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

    public function& getListRef($key)
    {
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = array();
        } else if (!is_array($this->storage[$key])) {
            throw new InvalidDatatypeException();
        }

        return $this->storage[$key];
    }

    public function getListOrNull($key)
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        return $this->getListRef($key);
    }

    public function getStringOrNull($key)
    {
        if (!isset($this->storage[$key])) {
            return null;
        }

        return $this->getStringRef($key);
    }

    public function& getStringRef($key)
    {
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = array();
        } else if (!is_string($this->storage[$key])) {
            throw new InvalidDatatypeException('WRONGTYPE Operation against a key holding the wrong kind of value');
        }

        return $this->storage[$key];
    }

    public function& getIntegerRef($key)
    {
        $value =& $this->accessInteger($key);

        if (is_numeric($value)) {
            throw new InvalidDatatypeException('ERR value is not an integer or out of range');
        }

        return $value;
    }
}
