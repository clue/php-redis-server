<?php

namespace Clue\Redis\Server;

use Clue\Redis\Protocol\Model\ErrorReply;
use Clue\Redis\Protocol\Model\StatusReply;
use ReflectionClass;
use ReflectionMethod;
use Exception;
use Clue\Redis\Protocol\Serializer\SerializerInterface;
use Clue\Redis\Protocol\Model\ModelInterface;
use Clue\Redis\Protocol\Model\Request;

class Invoker
{
    private $commands    = array();
    private $commandArgs = array();
    private $commandType = array();

    const TYPE_AUTO = 0;
    const TYPE_STRING_STATUS = 1;
    const TYPE_TRUE_STATUS = 2;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;

        foreach (array('ping', 'type') as $command) {
            $this->commandType[$command] = self::TYPE_STRING_STATUS;
        }

        foreach(array('set', 'setex', 'psetex', 'mset', 'rename', 'client', 'config', 'flushdb', 'flushall', 'auth', 'quit', 'select') as $command) {
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
        if ($n < $this->commandArgs[$command]) {
            return $this->serializer->getErrorMessage('ERR wrong number of arguments for \'' . $command . '\' command');
        }

        try {
            $ret = call_user_func_array($this->commands[$command], $args);
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

    public function addCommand($name, $callback)
    {

    }

    public function renameCommand($oldname, $newname)
    {
        if ($oldname === $newname || !isset($this->commands[$oldname])) {
        }
        $this->commands[$newname] = $this->commands[$oldname];
        $this->commandArgs[$newname] = $this->commandArgs[$oldname];
        unset($this->commandArgs[$oldname], $this->commands[$oldname]);

        if (isset($this->commandType[$oldname])) {
            $this->commandType[$newname] = $this->commandType[$oldname];
            unset($this->commandType[$oldname]);
        }
    }

    public function addCommands($class)
    {
        $ref = new ReflectionClass($class);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            /* @var $method ReflectionMethod */
            $name = $method->getName();
            if (substr($name, 0, 2) === '__') {
                continue;
            }

            $this->commands[$name] = array($class, $name);
            $this->commandArgs[$name] = $this->getNumberOfArguments($method);
        }
    }
}
