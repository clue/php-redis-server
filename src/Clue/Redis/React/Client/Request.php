<?php

namespace Clue\Redis\React\Client;

use Clue\Redis\Protocol\Model\ErrorReply;
use React\Promise\Deferred;

class Request extends Deferred
{
    public function handleReply($data)
    {
        if ($data instanceof ErrorReply) {
            $this->reject($data);
        } else {
            $this->resolve($data->getValueNative());
        }
    }
}
