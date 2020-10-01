<?php

declare(strict_types=1);

namespace Clue\Redis\Server\Tests;

use PHPUnit\Framework\MockObject\MockObject;

(include_once __DIR__ . '/../vendor/autoload.php') or die(PHP_EOL . 'ERROR: composer autoloader not found, run "composer install" or see README for instructions' . PHP_EOL);

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function expectCallableOnce()
    {
        $mock = $this->createCallableMock();

        if (func_num_args() > 0) {
            $mock
                ->expects(static::once())
                ->method('__invoke')
                ->with(static::equalTo(func_get_arg(0)));
        } else {
            $mock
                ->expects(static::once())
                ->method('__invoke');
        }

        return $mock;
    }

    protected function expectCallableNever()
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects(static::never())
            ->method('__invoke');

        return $mock;
    }

    protected function expectCallableOnceParameter($type)
    {
        $mock = $this->createCallableMock();
        $mock
            ->expects(static::once())
            ->method('__invoke')
            ->with(static::isInstanceOf($type));

        return $mock;
    }

    /**
     * @see https://github.com/reactphp/react/blob/master/tests/React/Tests/Socket/TestCase.php (taken from reactphp/react)
     */
    protected function createCallableMock(): MockObject
    {
        return $this->getMockBuilder(CallableStub::class)->getMock();
    }

    protected function expectPromiseResolve($promise)
    {
        static::assertInstanceOf('React\Promise\PromiseInterface', $promise);

        $that = $this;
        $promise->then(null, function ($error) use ($that): void {
            $that->assertNull($error);
            $that->fail('promise rejected');
        });
        $promise->then($this->expectCallableOnce(), $this->expectCallableNever());

        return $promise;
    }

    protected function expectPromiseReject($promise)
    {
        static::assertInstanceOf(\React\Promise\PromiseInterface::class, $promise);

        $that = $this;
        $promise->then(function ($value) use ($that): void {
            $that->assertNull($value);
            $that->fail('promise resolved');
        });

        $promise->then($this->expectCallableNever(), $this->expectCallableOnce());

        return $promise;
    }
}

class CallableStub
{
    public function __invoke(): void
    {
    }
}
