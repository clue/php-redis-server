# clue/redis-server [![Build Status](https://travis-ci.org/clue/redis-server.png?branch=master)](https://travis-ci.org/clue/redis-server)

A redis server implementation in pure PHP. *Not for the faint-hearted.*

> Note: This project is in early alpha stage! Feel free to report any issues you encounter.

## Introduction


### Motivation

This project aims to provide a simple alternative to the official redis server
implementation if installing it is not an option.

Why would I use this project if I already have the official redis server
installed? Simply put, you wouldn't. Ever.

### Project goals

* ✓ Implement an in-memory datastore using the redis protocol
* ✓ Compatiblity with common redis clients
  * ✓ redis-cli
  * ✓ redis-benchmark
* ✓ SOLID and modern design, tested and modular components
* ✗ Implement *all* commands (see below for list of supported commands)
* ✗ Compatibility with common tools resque(-php) etc.

### Supported commands

Eventually, this project aims to replicate *all* commands of
the [official redis server](http://redis.io/) implementation and their exact
behavior.

So far, the following list of commands shows what's already implemented:

* Keys
  * DEL
  * EXISTS
  * EXPIRE
  * EXPIREAT
  * KEYS
  * PERSIST
  * PEXPIRE
  * PEXPIREAT
  * PTTL
  * RANDOMKEY
  * RENAME
  * RENAMENX
  * SORT
  * TTL
  * TYPE
* Strings
  * APPEND
  * DECR
  * DECRBY
  * GET
  * GETRANGE
  * GETSET
  * INCR
  * INCRBY
  * MGET
  * MSET
  * MSETNX
  * PSETEX
  * SET
  * SETEX
  * SETNX
  * SETRANGE
  * STRLEN
* Lists
  * LINDEX
  * LLEN
  * LPOP
  * LPUSH
  * LPUSHX
  * LRANGE
  * RPOP
  * RPOPLPUSH
  * RPUSH
  * RPUSHX
* Connection
  * ECHO
  * PING
  
For details, refer to the excellent official documentation of
[redis commands](http://redis.io/commands).

All available commands are expected to behave just like their counterparts in
redis v2.6, unless otherwise noted. If you find a command to misbehave, don't
hesitate to file a bug.

Obviously, this list is incomplete in that it does not include *every* command
supported by redis. If you find a command is missing, please submit a PR :)

### Benchmarking performance

> As usual, just about *every* benchmark is biased - you've been warned.

We used the [official benchmark tool](http://redis.io/topics/benchmarks) `redis-benchmark` script that is included when installing the official redis server.

```bash
$ redis-benchmark -t set,get -q
```

Some benchmarking results:

```
# official redis-server
$ redis-server --port 1338
$ redis-benchmark -t set,get -p 1338 -q
SET: 121951.22 requests per second
GET: 151515.16 requests per second

# clue/redis-server PHP 5.5
$ php example/server.php
$ redis-benchmark -t set,get -p 1337 -q
SET: 18761.73 requests per second
GET: 22172.95 requests per second

# clue/redis-server HHVM
$ hhvm -vEval.Jit=true example/server.php
$ redis-benchmark -t set,get -p 1337 -q
SET: 49019.61 requests per second
GET: 57142.86 requests per second
```

So depending on your configuration, expect the original implementation to be
2x to 5x as fast. Some thoughts that have a significant effect on the
performance:

- HHVM is significantly faster than standard PHP (2.5x)
- Installing `ext-libevent` (not available for HHVM unfortunately) will
  significantly improve the performance for concurrent connections.
  This is not a hard requirement, but `redis-benchmark` defaults to 50
  concurrent connections which slows down the whole server process due to
  relying on a `stream_select()` call otherwise.
- The `example/server.php` includes a `$debug` flag (which defaults to `false`).
  Disabled debugging output significantly improves performance (3x)
- The benchmark should not be run from within a virtual machine. Running this on
  the host machine instead shows significant improvements (8x). For comparision,
  the same applies to official redis, although it shows a smaller impact (3x).

#### Benchmarking performance on AWS

Some facts about the machine:

* Instance Type: [c4.xlarge](https://aws.amazon.com/ec2/instance-types/?nc1=h_ls#compute-optimized) (16 ECUs, 4 vCPUs, 2.9 GHz, Intel Xeon E5-2666v3, 7.5 GiB memory, EBS only, compute optimized)
* Availability Zone: eu-central-1b (Frankfurt, Germany)
* AMI: Ubuntu Server 14.04 LTS (HVM), SSD Volume Type - ami-87564feb

Some benchmarking results:

```
# Official redis-server
# 	redis_version:2.8.4
# 	redis_build_id:a44a05d76f06a5d9
# 	redis_mode:standalone
# 	os:Linux 3.13.0-74-generic x86_64
# 	arch_bits:64
# 	gcc_version:4.8.2
# 	mem_allocator:jemalloc-3.4.1
$ redis-server
$ redis-benchmark -t set,get -q
SET: 181818.19 requests per second
GET: 185185.19 requests per second

# clue/redis-server
#	PHP 7.0.2-4+deb.sury.org~trusty+1 (cli) ( NTS )
#	Copyright (c) 1997-2015 The PHP Group
#	Zend Engine v3.0.0, Copyright (c) 1998-2015 Zend Technologies
#	    with Zend OPcache v7.0.6-dev, Copyright (c) 1999-2015, by Zend Technologies
$ php bin/redis-server.php
$ redis-benchmark -t set,get -q
SET: 78125.00 requests per second
GET: 75187.97 requests per second

# clue/redis-server
# 	HipHop VM 3.11.0 (rel)
#	Compiler: tags/HHVM-3.11.0-0-g3dd564a8cde23e3205a29720d3435c771274085e
#	Repo schema: 52047bdda550f21c2ec2fcc295e0e6d02407be51
$ hhvm -vEval.Jit=true bin/redis-server.php
$ redis-benchmark -t set,get -q
SET: 70921.98 requests per second
GET: 71942.45 requests per second
```

Some notes about the benchmark:

* Date of benchmark: 2016-01-22, ~9pm
* The instance was running on a shared instance. There might be more performance on a [dedicated instance](https://aws.amazon.com/ec2/purchasing-options/dedicated-instances/) or even on a [dedicated host](https://aws.amazon.com/ec2/dedicated-hosts/).
* PHP7 was not self-compiled. The precompiled version by [Ondřej Surý](https://github.com/oerdnj) was used. There might be more performance available by a custom compiled PHP version.
* The PHP-Extension [libevent](https://pecl.php.net/package/libevent) was not available for PHP 7 at the time of the benchmark.

## Quickstart example

Once [installed](#install), you can run any of the examples provided:

```bash
php example/server.php
```

Alternatively, you can also use this project as a lib in order to build your
own server like this:

```php

$factory = new Factory($loop, $connector);
$factory->createServer('localhost:1337')->then(function (Server $server) use ($loop) {
    $server->on('connection', function(Client $client) {
        echo $client->getRemoteAddr() .' connected' . PHP_EOL;    
    });
});

$loop->run();
```

## Install

The recommended way to install this library cloning this repo and installing
its dependencies [through composer](http://getcomposer.org). [New to composer?](http://getcomposer.org/doc/00-intro.md)

```bash
$ sudo apt-get install php5-cli git curl
$ git clone https://github.com/clue/redis-server.git
$ cd redis-server/
$ curl -s https://getcomposer.org/installer | php
$ php composer.phar install
```

## License

MIT

