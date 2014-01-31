<?php

namespace Clue\Redis\Server;

use Clue\Redis\Server\Invoker;
use Clue\Redis\Protocol\Model\Request;
use Clue\Redis\Server\Client;

class AuthInvoker extends Invoker
{
    private $invoker;

    public function __construct(Invoker $successfulInvoker)
    {
        $this->invoker = $successfulInvoker;
    }

    public function getSuccessfulInvoker()
    {
        return $this->invoker;
    }

    public function invoke(Request $request, Client $client)
    {
        $command = strtolower($request->getCommand());

        if ($command !== 'auth') {
            // should be after checking number of args:
            return $this->invoker->getSerializer()->getErrorMessage('ERR operation not permitted');
        }

        return $this->invoker->invoke($request, $client);
    }
}
