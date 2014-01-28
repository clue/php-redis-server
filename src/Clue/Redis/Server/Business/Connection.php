<?php

namespace Clue\Redis\Server\Business;

class Connection
{
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
