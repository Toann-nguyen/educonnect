<?php

namespace App\Domains\Shared\Bus;

interface CommandBus
{
    public function dispatch(object $command): mixed;
}
