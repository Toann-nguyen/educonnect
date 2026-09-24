<?php

namespace App\Domains\Shared\Bus;

use Closure;
use InvalidArgumentException;

final class InMemoryQueryBus implements QueryBus
{
    private array $handlers = [];

    public function register(string $queryClass, Closure $handler): self
    {
        $this->handlers[$queryClass] = $handler;

        return $this;
    }

    public function dispatch(object $query): mixed
    {
        $class = $query::class;
        if (! isset($this->handlers[$class])) {
            throw new InvalidArgumentException("No query handler registered for {$class}");
        }

        return ($this->handlers[$class])($query);
    }
}
