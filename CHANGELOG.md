# CHANGELOG

This file is a manually maintained list of changes for each release. Feel free
to add your changes here when sending pull requests. Also send corrections if
you spot any mistakes.

## 0.1.0 (2014-09-xx)

* Feature: New commands
  * LINDEX
  * LPUSHX/RPUSHX
  * RPOPLPUSH
  * KEYS
  * RANDOMKEY
  * SORT
  * LRANGE
  * SELECT
  * QUIT
  * CLIENT
  * DBSIZE
  * FLUSHDB
  * FLUSHALL
  * SHUTDOWN
  * TIME
  * CONFIG
  * AUTH

* Feature: Support old inline protocol by using updated protocol parser clue/redis-protocol:v0.3
  (#3 / #4)
  * Support running the full `redis-benchmark` suite
  * Significant performance improvment
  * Commands are now case-insensitive

* Feature: Defaults to listening on `0.0.0.0:6378`
  (#7)
  * Add `--port` argument to pass port to example server
  * Print error to console if starting listening server socket fails

* Feature: Add support for `requirepass` config option (AUTH command) 

* Feature: Validate all integer arguments (timeouts, increments etc.)

* Feature: Support binding to random port when passing `0` port

* Feature: Refactor to support database per user (SELECT command)

* Feature: Significant performance improvement for list operations by using `SplDoublyLinkedList` internally

* Feature: Update dependencies to support React v0.4 and react/promise:v2.0

* Fix: Reversed insertion order for LPUSH with multiple values

* Fix: Fix processing of EXPIRE/PEXPIRE timeout values

* Removed debugging output from example server

* Update homepage, use PSR-4 code layout

* Test against HVVM
  (#2 by @ptarjan)

## 0.0.2 (2014-01-22)

* First alpha release, dedicated server component split from clue/redis-react
* Actually interpret each request and reply with a meaningful reply
* Includes a whole bunch of redis commands (30+)

## 0.0.1 (2013-07-21)

* First proof of concept: a server that rejects every request.

