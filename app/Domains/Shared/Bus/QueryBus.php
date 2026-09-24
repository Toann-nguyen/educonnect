<?php

namespace App\Domains\Shared\Bus;

interface QueryBus
{
    public function dispatch(object $query): mixed;
}
