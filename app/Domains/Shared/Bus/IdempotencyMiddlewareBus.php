<?php

namespace App\Domains\Shared\Bus;

use Closure;

final class IdempotencyMiddlewareBus
{
    public function __construct(
        private readonly CommandBus $inner,
        private readonly Closure $lock,
    ) {}

    public function dispatch(object $command): mixed
    {
        $key = property_exists($command, 'idempotencyKey') ? $command->idempotencyKey : null;
        if ($key === null || $key === '') {
            return $this->inner->dispatch($command);
        }

        return ($this->lock)($key, fn () => $this->inner->dispatch($command));
    }
}
