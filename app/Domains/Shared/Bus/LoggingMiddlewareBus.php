<?php

namespace App\Domains\Shared\Bus;

use Psr\Log\LoggerInterface;

final class LoggingMiddlewareBus
{
    public function __construct(
        private readonly CommandBus $inner,
        private readonly LoggerInterface $logger,
    ) {}

    public function dispatch(object $command): mixed
    {
        $this->logger->info('command.dispatch', ['command' => $command::class]);

        return $this->inner->dispatch($command);
    }
}
