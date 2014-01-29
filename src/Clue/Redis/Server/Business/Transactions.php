<?php

namespace Clue\Redis\Server\Business;

use Clue\Redis\Protocol\Model\Request;
use Clue\Redis\Server\Client;
class Transactions
{
    // StatusReply
    public function discard()
    {
        if ($this->multi === -1) {
            throw new UnexpectedValueException('ERR DISCARD without MULTI');
        }

        $this->unwatch();
        return true;
    }

    // MultiBulkReply
    public function exec()
    {
        if ($this->multi === -1) {
            throw new UnexpectedValueException('ERR EXEC without MULTI');
        }

        if ($this->watchTouched) {
            // clear transaction anyway
            return null;
        }

        $ret = array();
        foreach ($this->transaction as $command) {
            $ret []= $this->invoker->invoke($command);
        }

        $this->transaction = null;

        $this->unwatch();

        return $ret;
    }

    // StatusReply
    public function multi()
    {
        if ($this->multi !== -1) {
            throw new UnexpectedValueException('ERR MULTI calls can not be nested');
        }
        $this->multi = 0;
        $this->watchTouched = false;

        return true;
    }

    // StatusReply
    public function unwatch()
    {

        return true;
    }

    // StatusReply
    public function watch($key0)
    {
        if ($this->watchTouched) {
            // already changed anyway => no need to keep watching
            return;
        }
        $keys = func_get_args();

        foreach ($keys as $key) {
            $this->database->onTouch($key, function() {

            });
        }
    }

    // StatusReply-Text
    private function enqueue(Request $request)
    {
        if ($this->checkArgs($request)) {

        }
        return 'QUEUED';
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
    }

    private function getClient()
    {
        if ($this->client === null) {
            throw new UnexpectedValueException('Invalid state');
        }
        return $this->client;
    }
}
