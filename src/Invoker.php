<?php

declare(strict_types=1);

namespace Clue\Redis\Server;

use Clue\Redis\Protocol\Model\Request;
use Clue\Redis\Protocol\Serializer\SerializerInterface;
use Exception;
use ReflectionClass;
use ReflectionMethod;

class Invoker
{
    const TYPE_AUTO = 0;
    const TYPE_STRING_STATUS = 1;
    const TYPE_TRUE_STATUS = 2;

    private SerializerInterface $serializer;

    private array $commands = [];

    private array $commandArgs = [];

    private array $commandType = [];

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;

        foreach (['ping', 'type'] as $command) {
            $this->commandType[$command] = self::TYPE_STRING_STATUS;
        }

        foreach (['set', 'setex', 'psetex', 'mset', 'rename', 'client', 'config', 'flushdb', 'flushall', 'auth', 'quit', 'select'] as $command) {
            $this->commandType[$command] = self::TYPE_TRUE_STATUS;
        }
    }

    public function invoke(Request $request, Client $client): string
    {
        $command = mb_strtolower($request->getCommand());
        $args = $request->getArgs();

        if (!isset($this->commands[$command])) {
            return $this->serializer->getErrorMessage('ERR Unknown or disabled command \'' . $command . '\'');
        }

        $n = count($args);
        if ($n < $this->commandArgs[$command]) {
            return $this->serializer->getErrorMessage('ERR wrong number of arguments for \'' . $command . '\' command');
        }

        // This doesn't even deserve a proper comment…
        $b = reset($this->commands[$command]);
        if (is_callable([$b, 'setClient'])) {
            $b->setClient($client);
        }

        try {
            $ret = call_user_func_array($this->commands[$command], $args);
        } catch (Exception $e) {
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

    public function getSerializer(): SerializerInterface
    {
        return $this->serializer;
    }

    public function addCommand(string $name, callable $callback): void
    {
    }

    public function renameCommand(string $oldname, string $newname): void
    {
        if ($oldname === $newname || !isset($this->commands[$oldname])) {
            return;
        }

        $this->commands[$newname] = $this->commands[$oldname];
        $this->commandArgs[$newname] = $this->commandArgs[$oldname];
        unset($this->commandArgs[$oldname], $this->commands[$oldname]);

        if (isset($this->commandType[$oldname])) {
            $this->commandType[$newname] = $this->commandType[$oldname];
            unset($this->commandType[$oldname]);
        }
    }

    public function addCommands($class): void
    {
        $ref = new ReflectionClass($class);
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            /* @var $method ReflectionMethod */
            $name = $method->getName();
            if (mb_substr($name, 0, 2) === '__' || $name === 'setClient') {
                continue;
            }

            $this->commands[$name] = [$class, $name];
            $this->commandArgs[$name] = $this->getNumberOfArguments($method);
        }
    }

    private function getNumberOfArguments(ReflectionMethod $method): int
    {
        return $method->getNumberOfRequiredParameters();
    }
}
