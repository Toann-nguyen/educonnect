<?php

namespace Tests\Unit;

use App\Domains\Identity\Models\User;
use App\Domains\School\Application\Grades\Commands\CreateGradeCommand;
use App\Domains\School\Application\Grades\Queries\GetMyGradesQuery;
use App\Domains\Shared\Bus\InMemoryCommandBus;
use App\Domains\Shared\Bus\InMemoryQueryBus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SchoolCqrsTest extends TestCase
{
    public function test_command_bus_dispatches_registered_handler(): void
    {
        $bus = new InMemoryCommandBus;
        $bus->register(CreateGradeCommand::class, fn (CreateGradeCommand $command) => $command->data);

        $user = $this->createStub(User::class);
        $result = $bus->dispatch(new CreateGradeCommand(['score' => 9], $user));

        $this->assertSame(['score' => 9], $result);
    }

    public function test_query_bus_throws_for_unknown_query(): void
    {
        $bus = new InMemoryQueryBus;

        $this->expectException(InvalidArgumentException::class);
        $user = $this->createStub(User::class);
        $bus->dispatch(new GetMyGradesQuery($user));
    }
}
