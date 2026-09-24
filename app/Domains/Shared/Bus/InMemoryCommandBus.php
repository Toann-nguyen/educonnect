<?php

namespace App\Domains\Shared\Bus;

use Closure;
use InvalidArgumentException;

final class InMemoryCommandBus implements CommandBus
{
    private array $handlers = [];

    public function register(string $commandClass, Closure $handler): self
    {
        $this->handlers[$commandClass] = $handler;

        return $this;
    }

    public function dispatch(object $command): mixed
    {
        $class = $command::class;
        if (! isset($this->handlers[$class])) {
            throw new InvalidArgumentException("No command handler registered for {$class}");
        }

        return ($this->handlers[$class])($command);
    }
}
