<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Client;
use Clue\Redis\Server\Storage;
use Exception;

class Keys
{
    private Storage $storage;

    public function __construct(?Storage $storage = null)
    {
        $this->storage = $storage ?? new Storage();
    }

    public function keys(string $pattern): array
    {
        $ret = [];

        foreach ($this->storage->getAllKeys() as $key) {
            if (fnmatch($pattern, $key)) {
                $ret[] = $key;
            }
        }

        return $ret;
    }

    public function randomkey()
    {
        return $this->storage->getRandomKey();
    }

    public function sort(string $key)
    {
        if ($this->storage->hasKey($key)) {
            $list = [...$this->storage->getOrCreateList($key)];
        } else {
            // no need to sort, but validate arguments in order to be consistent with reference implementation
            $list = [];
        }

        $by = null;
        $offset = null;
        $count = null;
        $get = [];
        $desc = false;
        $sort = SORT_NUMERIC;
        $store = null;

        $args = func_get_args();
        $next = function () use (&$args, &$i) {
            if (!isset($args[$i + 1])) {
                throw new Exception('ERR syntax error');
            }

            return $args[++$i];
        };
        for ($i = 1, $l = count($args); $i < $l; ++$i) {
            $arg = mb_strtoupper($args[$i]);

            if ($arg === 'BY') {
                $by = $next();
            } elseif ($arg === 'LIMIT') {
                $offset = $this->coerceInteger($next());
                $count = $this->coerceInteger($next());
            } elseif ($arg === 'GET') {
                $get[] = $next();
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
            $cmp = function ($a, $b) {
                $fa = (float) $a;
                $fb = (float) $b;
                if ((string) $fa !== $a || (string) $fb !== $b) {
                    throw new Exception('ERR One or more scores can\'t be converted into double');
                }

                return ($fa < $fb) ? -1 : (($fa > $fb) ? 1 : 0);
            };
        } else {
            $cmp = 'strcmp';
        }

        if ($by !== null) {
            $pos = mb_strpos($by, '*');
            if ($pos === false) {
                $cmp = null;
            } else {
                $cmp = 'strcmp';

                $lookup = [];
                foreach (array_unique($list) as $v) {
                    $key = str_replace('*', $v, $by);
                    $lookup[$v] = $this->storage->getStringOrNull($key);
                    if ($lookup[$v] === null) {
                        $lookup[$v] = $v;
                    }
                }

                $cmp = fn ($a, $b) => $cmp($lookup[$a], $lookup[$b]);
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
            $lookup = function ($key, $pattern) use ($storage) {
                if ($pattern === '#') {
                    return $key;
                }

                $l = str_replace('*', $key, $pattern);

                static $cached = [];

                if (!array_key_exists($l, $cached)) {
                    $cached[$l] = $storage->getStringOrNull($l);
                }

                return $cached[$l];
            };
            $keys = $list;
            $list = [];

            foreach ($keys as $key) {
                foreach ($get as $pattern) {
                    $list[] = $lookup($key, $pattern);
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

    public function persist(string $key): bool
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

    public function expire(string $key, int $seconds): bool
    {
        return $this->pexpireat($key, (int) (1_000 * (microtime(true) + $seconds)));
    }

    public function expireat(string $key, int $timestamp): bool
    {
        return $this->pexpireat($key, 1_000 * $timestamp);
    }

    public function pexpire(string $key, int $milliseconds): bool
    {
        return $this->pexpireat($key, (int) (1_000 * microtime(true)) + $milliseconds);
    }

    public function pexpireat(string $key, int $millisecondTimestamp): bool
    {
        if (!$this->storage->hasKey($key)) {
            return false;
        }
        $this->storage->setTimeout($key, (int) ($millisecondTimestamp / 1_000));

        return true;
    }

    public function ttl(string $key): int
    {
        $pttl = $this->pttl($key);
        if ($pttl > 0) {
            $pttl = (int) ($pttl / 1_000);
        }

        return $pttl;
    }

    public function pttl(string $key): int
    {
        if (!$this->storage->hasKey($key)) {
            return -2;
        }

        $timeout = $this->storage->getTimeout($key);
        if ($timeout === null) {
            return -1;
        }

        $milliseconds = 1_000 * ($timeout - microtime(true));
        if ($milliseconds < 0) {
            $milliseconds = 0;
        }

        return (int) $milliseconds;
    }

    public function del(string $key): int
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

    public function exists(string $key): bool
    {
        return $this->storage->hasKey($key);
    }

    // StatusReply
    public function rename(string $key, string $newkey): bool
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

    public function renamenx(string $key, string $newkey): bool
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
    public function type(string $key): string
    {
        if (!$this->storage->hasKey($key)) {
            return 'none';
        }

        $value = $this->storage->get($key);
        if (is_string($value)) {
            return 'string';
        } elseif ($value instanceof \SplDoublyLinkedList) {
            return 'list';
        }
        throw new \UnexpectedValueException('Unknown datatype encountered');
    }

    public function setClient(Client $client): void
    {
        $this->storage = $client->getDatabase();
    }

    private function coerceInteger($value): int
    {
        $int = (int) $value;
        if ((string) $int !== (string) $value) {
            throw new Exception('ERR value is not an integer or out of range');
        }

        return $int;
    }
}
