<?php

declare(strict_types=1);

namespace Clue\Redis\Server;

use Clue\Redis\Protocol\Model\Request;

class AuthInvoker extends Invoker
{
    private Invoker $invoker;

    public function __construct(Invoker $successfulInvoker)
    {
        $this->invoker = $successfulInvoker;
    }

    public function getSuccessfulInvoker(): Invoker
    {
        return $this->invoker;
    }

    public function invoke(Request $request, Client $client): string
    {
        $command = mb_strtolower($request->getCommand());

        if ($command !== 'auth') {
            // should be after checking number of args:
            return $this->invoker->getSerializer()->getErrorMessage('ERR operation not permitted');
        }

        return $this->invoker->invoke($request, $client);
    }
}
