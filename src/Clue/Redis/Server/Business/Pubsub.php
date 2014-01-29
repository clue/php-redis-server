<?php

namespace Clue\Redis\Server\Business;

use Clue\Redis\Server\Client;
use InvalidArgumentException;

class Pubsub
{
    public function publish($channel, $message)
    {
        $clients = $this->getClientsSubscribedToChannel($channel);

        if ($clients) {
            $data = $this->serialize(array(
                'message',
                $channel,
                $message,
            ));

            foreach ($clients as $client) {
                $client->send($data);
            }
        }

        // foreach pattern
        // $this->serialize('pmessage', $pattern, $channel, $message)

        return count($clients);
    }

    private function serialize($data)
    {
        return $data = $this->getClient()->getBusiness()->getSerializer()->getMessageReply($data);
    }

    public function pubsub($subcommand)
    {
        $subcommand = strtolower($subcommand);
        $n = func_num_args();

        if ($subcommand === 'channels' && ($n === 1 || $n === 2)) {
            $pattern = ($n === 2) ? func_get_arg(1) : null;
            return $this->getChannelsMatching($pattern);
        } elseif ($subcommand === 'numsub') {
            $names = func_get_args();
            array_shift($names);

            $ret = array();
            foreach ($names as $name) {
                $ret [] = $name;
                $ret [] = $this->getNumberOfSubscribersForChannel($name);
            }
            return $ret;
        } elseif ($subcommand === 'numpat' && $n === 1) {
            return $this->getNumberOfPatterns();
        } else {
            throw new InvalidArgumentException('ERR Unknown PUBSUB subcommand or wrong number of arguments for \'pubsub\'');
        }
    }

    // Multi-Multi!
    public function subscribe($channel0)
    {
        $channels = func_get_args();

        $ret = array();

        foreach ($channels as $channel) {
            $n = $this->subscribeClientToChannel($this->client, $channel);

            $ret []= array('subscribe', $channel, $n);
        }

        return $ret;
    }

    // Multi-Multi!
    public function unsubscribe()
    {
        if (func_num_args() === 0) {
            $channels = $this->getChannelsForClient($this->client);
            if (!$channels) {
                return array(array('unsubscribe', null, 0));
            }
        } else {
            $channels = func_get_args();
        }

        $ret = array();

        foreach ($channels as $channel) {
            $n = $this->unsubscribeClientFromChannel($this->client, $channel);

            $ret []= array('unsubscribe', $channel, $n);
        }

        return $ret;
    }

    private function getChannelsMatching($pattern)
    {

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

    private function getNumberOfPatterns()
    {
        return 0;
    }

    private function getNumberOfSubscribersForChannel($channel)
    {
        return count($this->getClientsSubscribedToChannel($channel));
    }

    private function getClientsSubscribedToChannel($channel)
    {
        return array();
    }

    private function subscribeClientToChannel(Client $client, $channel)
    {
        return 1;
    }

    private function unsubscribeClientFromChannel(Client $client, $channel)
    {
        return 0;
    }

    private function getChannelsForClient(Client $client)
    {
        return array();
    }
}
