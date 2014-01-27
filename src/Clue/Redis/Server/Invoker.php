<?php

namespace Clue\Redis\Server;

use Clue\Redis\Protocol\Model\ErrorReply;
use ReflectionClass;
use ReflectionMethod;
use Exception;
use Clue\Redis\Protocol\Serializer\SerializerInterface;
use Clue\Redis\Protocol\Model\ModelInterface;
use Clue\Redis\Protocol\Model\Request;

class Invoker
{
    private $business;
    private $commands = array();
    private $commandType = array();

    const TYPE_AUTO = 0;
    const TYPE_STRING_STATUS = 1;
    const TYPE_TRUE_STATUS = 2;

    public function __construct($business, SerializerInterface $serializer)
    {
        $this->business = $business;
        $this->serializer = $serializer;

        $ref = new ReflectionClass($business);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            /* @var $method ReflectionMethod */
            $this->commands[$method->getName()] = $this->getNumberOfArguments($method);
        }

        foreach (array('ping', 'set', 'setex', 'psetex', 'type') as $command) {
            $this->commandType[$command] = self::TYPE_STRING_STATUS;
        }

        foreach(array('set', 'setex', 'psetex', 'mset', 'rename') as $command) {
            $this->commandType[$command] = self::TYPE_TRUE_STATUS;
        }
    }

    private function getNumberOfArguments(ReflectionMethod $method)
    {
        return $method->getNumberOfRequiredParameters();
    }

    public function invoke(Request $request)
    {
        $command = strtolower($request->getCommand());
        $args    = $request->getArgs();

        if (!isset($this->commands[$command])) {
            return $this->serializer->getErrorMessage('ERR Unknown or disabled command \'' . $command . '\'');
        }

        $n = count($args);
        if ($n < $this->commands[$command]) {
            return $this->serializer->getErrorMessage('ERR wrong number of arguments for \'' . $command . '\' command');
        }

        try {
            $ret = call_user_func_array(array($this->business, $command), $args);
        }
        catch (Exception $e) {
            return $this->serializer->getErrorMessage($e->getMessage());
        }

        if (isset($this->commandType[$command])) {
            if ($this->commandType[$command] === self::TYPE_STRING_STATUS && is_string($ret)) {
                return $this->serializer->getStatusMessage($ret);
            } elseif ($this->commandType[$command] === self::TYPE_TRUE_STATUS && $ret === true) {
                return $this->serializer->getStatusMessage('OK');
            }
        }

        return $this->serializer->getReplyMessage($ret);
    }
}