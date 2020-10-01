<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Client;
use Clue\Redis\Server\Storage;
use Exception;
use InvalidArgumentException;

class Strings
{
    private Storage $storage;

    public function __construct(?Storage $storage = null)
    {
        $this->storage = $storage ?? new Storage();
    }

    public function append(string $key, $value): int
    {
        $string = &$this->storage->getStringRef($key);
        $string .= $value;

        return mb_strlen($string);
    }

    public function get(string $key): ?string
    {
        return $this->storage->getStringOrNull($key);
    }

    // StatusReply
    public function set(string $key, $value): ?bool
    {
        if (func_num_args() > 2) {
            $args = func_get_args();
            array_shift($args);
            array_shift($args);

            $px = null;
            $ex = null;
            $xx = false;
            $nx = false;

            for ($i = 0, $n = count($args); $i < $n; ++$i) {
                $arg = mb_strtoupper($args[$i]);

                if ($arg === 'XX') {
                    $xx = true;
                } elseif ($arg === 'NX') {
                    $nx = true;
                } elseif ($arg === 'EX' || $arg === 'PX') {
                    if (!isset($args[$i + 1])) {
                        throw new InvalidArgumentException('ERR syntax error');
                    }
                    $num = $this->coerceInteger($args[++$i]);
                    if ($num <= 0) {
                        throw new InvalidArgumentException('ERR invalid expire time in SETEX');
                    }

                    if ($arg === 'EX') {
                        $ex = $num;
                    } else {
                        $px = $num;
                    }
                } else {
                    throw new InvalidArgumentException('ERR syntax error');
                }
            }

            if ($nx && $this->storage->hasKey($key)) {
                return null;
            }

            if ($xx && !$this->storage->hasKey($key)) {
                return null;
            }

            if ($ex !== null) {
                $px += $ex * 1_000;
            }

            if ($px !== null) {
                return $this->psetex($key, $px, $value);
            }
        }

        $this->storage->setString($key, $value);

        return true;
    }

    // StatusReply
    public function setex(string $key, int $seconds, $value): bool
    {
        return $this->psetex($key, $seconds * 1_000, $value);
    }

    // StatusReply
    public function psetex(string $key, int $milliseconds, $value): bool
    {
        $this->storage->setString($key, $value);
        $this->storage->setTimeout($key, microtime(true) + ($milliseconds / 1_000));

        return true;
    }

    public function setnx(string $key, $value): bool
    {
        if ($this->storage->hasKey($key)) {
            return false;
        }

        $this->storage->setString($key, $value);

        return true;
    }

    public function incr(string $key): int
    {
        return $this->incrby($key, 1);
    }

    public function incrby(string $key, int $increment): int
    {
        $value = $this->storage->getIntegerOrNull($key);
        $value += $increment;

        $this->storage->setString($key, $value);

        return $value;
    }

    public function decr(string $key): int
    {
        return $this->incrby($key, -1);
    }

    public function decrby(string $key, int $decrement): int
    {
        return $this->incrby($key, -$decrement);
    }

    public function getset(string $key, $value): ?string
    {
        $old = $this->storage->getStringOrNull($key);
        $this->storage->setString($key, $value);

        return $old;
    }

    public function getrange(string $key, int $start, int $end): string
    {
        $string = $this->storage->getStringOrNull($key) ?? '';

        if ($end > 0) {
            $end = $end - $start + 1;
        } elseif ($end < 0) {
            if ($start < 0) {
                $end = $end - $start + 1;
            } else {
                ++$end;
                if ($end === 0) {
                    return $string;
                }
            }
        }

        return (string) mb_substr($string, $start, $end);
    }

    public function setrange(string $key, int $offset, $value): int
    {
        $string = &$this->storage->getStringRef($key);
        $slen = mb_strlen($string);

        $post = '';

        if ($slen < $offset) {
            $string .= str_repeat("\0", $offset - $slen);
        } else {
            $post = (string) mb_substr($string, $offset + mb_strlen($value));
            $string = mb_substr($string, 0, $offset);
        }

        $string .= $value . $post;

        return mb_strlen($string);
    }

    public function mget(string $key): array
    {
        $keys = func_get_args();
        $ret = [];

        foreach ($keys as $key) {
            try {
                $ret[] = $this->storage->getStringOrNull($key);
            } catch (Exception $ignore) {
                $ret[] = null;
            }
        }

        return $ret;
    }

    // StatusReply
    public function mset(string $key, $value): bool
    {
        $n = func_num_args();
        if ($n & 1) {
            throw new Exception('ERR wrong number of arguments for \'mset\' command');
        }

        $args = func_get_args();
        for ($i = 0; $i < $n; $i += 2) {
            $this->storage->setString($args[$i], $args[$i + 1]);
        }

        return true;
    }

    public function msetnx(string $key, $value): bool
    {
        for ($i = 0, $n = func_num_args(); $i < $n; $i += 2) {
            if ($this->storage->hasKey(func_get_arg($i))) {
                return false;
            }
        }

        call_user_func_array([$this, 'mset'], func_get_args());

        return true;
    }

    public function strlen(string $key): int
    {
        return mb_strlen($this->storage->getStringOrNull($key) ?? '');
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
