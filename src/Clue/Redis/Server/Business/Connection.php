<?php

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Server;

class Connection
{
    private $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function x_echo($message)
    {
        return $message;
    }

    // StatusReply
    public function ping()
    {
        return 'PONG';
    }
}
