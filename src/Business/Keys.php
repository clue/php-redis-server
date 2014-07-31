<?php

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Storage;
use Exception;
use InvalidArgumentException;
use Clue\Redis\Server\Client;

class Keys
{
    private $storage;

    public function __construct(Storage $storage = null)
    {
        if ($storage === null) {
            $storage = new Storage();
        }
        $this->storage = $storage;
    }

    public function keys($pattern)
    {
        $ret = array();

        foreach ($this->storage->getAllKeys() as $key) {
            if(fnmatch($pattern, $key)) {
                $ret []= $key;
            }
        }

        return $ret;
    }

    public function randomkey()
    {
        return $this->storage->getRandomKey();
    }

    public function sort($key)
    {
        if ($this->storage->hasKey($key)) {
            $list = iterator_to_array($this->storage->getOrCreateList($key), false);
        } else {
            // no need to sort, but validate arguments in order to be consistent with reference implementation
            $list = array();
        }

        $by     = null;
        $offset = null;
        $count  = null;
        $get    = array();
        $desc   = false;
        $sort   = SORT_NUMERIC;
        $store  = null;

        $args = func_get_args();
        $next = function() use (&$args, &$i) {
            if (!isset($args[$i + 1])) {
                throw new Exception('ERR syntax error');
            }
            return $args[++$i];
        };
        for ($i = 1, $l = count($args); $i < $l; ++$i) {
            $arg = strtoupper($args[$i]);

            if ($arg === 'BY') {
                $by = $next();
            } elseif ($arg === 'LIMIT') {
                $offset = $this->coerceInteger($next());
                $count  = $this->coerceInteger($next());
            } elseif ($arg === 'GET') {
                $get []= $next();
            } elseif ($arg === 'ASC' || $arg === 'DESC') {
                $desc = ($arg === 'DESC');
            } elseif ($arg === 'ALPHA') {
                $sort = SORT_STRING;
            } elseif ($arg === 'STORE') {
                $store = $next();
            } else {
                throw new Exception('ERR syntax error');
            }
        }

        if ($sort === SORT_NUMERIC) {
            $cmp = function($a, $b) {
                $fa = (float)$a;
                $fb = (float)$b;
                if ((string)$fa !== $a || (string)$fb !== $b) {
                    throw new Exception('ERR One or more scores can\'t be converted into double');
                }
                return ($fa < $fb) ? -1 : (($fa > $fb) ? 1 : 0);
            };
        } else {
            $cmp = 'strcmp';
        }

        if ($by !== null) {
            $pos = strpos($by, '*');
            if ($pos === false) {
                $cmp = null;
            } else {
                $cmp = 'strcmp';

                $lookup = array();
                foreach (array_unique($list) as $v) {
                    $key = str_replace('*', $v, $by);
                    $lookup[$v] = $this->storage->getStringOrNull($key);
                    if ($lookup[$v] === null) {
                        $lookup[$v] = $v;
                    }
                }

                $cmp = function($a, $b) use ($cmp, $lookup){
                    return $cmp($lookup[$a], $lookup[$b]);
                };
            }
        }

        if ($cmp !== null) {
            usort($list, $cmp);
        }

        if ($desc) {
            $list = array_reverse($list);
        }

        if ($offset !== null) {
            $list = array_slice($list, $offset, $count, false);
        }

        if ($get) {
            $storage = $this->storage;
            $lookup = function($key, $pattern) use ($storage) {
                if ($pattern === '#') {
                    return $key;
                }

                $l = str_replace('*', $key, $pattern);

                static $cached = array();

                if (!array_key_exists($l, $cached)) {
                    $cached[$l] = $storage->getStringOrNull($l);
                }

                return $cached[$l];
            };
            $keys = $list;
            $list = array();

            foreach ($keys as $key) {
                foreach ($get as $pattern) {
                    $list []= $lookup($key, $pattern);
                }
            }
        }

        if ($store !== null) {
            $this->storage->unsetKey($store);

            if (!$list) {
                return 0;
            }

            $sl = $this->storage->getOrCreateList($store);

            foreach ($list as $value) {
                $sl->push($value);
            }

            return $sl->count();
        }

        return $list;
    }

    public function persist($key)
    {
        if ($this->storage->hasKey($key)) {
            $timeout = $this->storage->getTimeout($key);

            if ($timeout !== null) {
                $this->storage->setTimeout($key, null);
                return true;
            }
        }
        return false;
    }

    public function expire($key, $seconds)
    {
        return $this->pexpireat($key, (int)(1000 * (microtime(true) + $this->coerceInteger($seconds))));
    }

    public function expireat($key, $timestamp)
    {
        return $this->pexpireat($key, 1000 * $this->coerceInteger($timestamp));
    }

    public function pexpire($key, $milliseconds)
    {
        return $this->pexpireat($key, (int)(1000 * microtime(true)) + $this->coerceInteger($milliseconds));
    }

    public function pexpireat($key, $millisecondTimestamp)
    {
        $millisecondTimestamp = $this->coerceInteger($millisecondTimestamp);

        if (!$this->storage->hasKey($key)) {
            return false;
        }
        $this->storage->setTimeout($key, $millisecondTimestamp / 1000);

        return true;
    }

    public function ttl($key)
    {
        $pttl = $this->pttl($key);
        if ($pttl > 0) {
            $pttl = (int)($pttl / 1000);
        }
        return $pttl;
    }

    public function pttl($key)
    {
        if (!$this->storage->hasKey($key)) {
            return -2;
        }

        $timeout = $this->storage->getTimeout($key);
        if ($timeout === null) {
            return -1;
        }

        $milliseconds = 1000 * ($timeout - microtime(true));
        if ($milliseconds < 0) {
            $milliseconds = 0;
        }

        return (int)$milliseconds;
    }

    public function del($key0)
    {
        $n = 0;

        foreach (func_get_args() as $key) {
            if ($this->storage->hasKey($key)) {
                $this->storage->unsetKey($key);
                ++$n;
            }
        }

        return $n;
    }

    public function exists($key)
    {
        return $this->storage->hasKey($key);
    }

    // StatusReply
    public function rename($key, $newkey)
    {
        if ($key === $newkey) {
            throw new Exception('ERR source and destination objects are the same');
        } elseif (!$this->storage->hasKey($key)) {
            throw new Exception('ERR no such key');
        }

        if ($this->storage->hasKey($newkey)) {
            $this->del($newkey);
        }

        $this->storage->rename($key, $newkey);

        return true;
    }

    public function renamenx($key, $newkey)
    {
        if ($key === $newkey) {
            throw new Exception('ERR source and destination objects are the same');
        } elseif (!$this->storage->hasKey($key)) {
            throw new Exception('ERR no such key');
        }

        if ($this->storage->hasKey($newkey)) {
            return false;
        }

        $this->storage->rename($key, $newkey);

        return true;
    }

    // StatusReply
    public function type($key)
    {
        if (!$this->storage->hasKey($key)) {
            return 'none';
        }

        $value = $this->storage->get($key);
        if (is_string($value)) {
            return 'string';
        } elseif ($value instanceof \SplDoublyLinkedList) {
            return 'list';
        } else {
            throw new UnexpectedValueException('Unknown datatype encountered');
        }
    }

    private function coerceInteger($value)
    {
        $int = (int)$value;
        if ((string)$int !== (string)$value) {
            throw new Exception('ERR value is not an integer or out of range');
        }
        return $int;
    }

    public function setClient(Client $client)
    {
        $this->storage = $client->getDatabase();
    }
}
