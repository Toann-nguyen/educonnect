<?php

namespace App\Providers;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Repositories\Auth\AuthRepository;
use App\Domains\Identity\Repositories\Auth\EmailVerificationRepository;
use App\Domains\Identity\Repositories\Contracts\AuthRepositoryInterface;
use App\Domains\Identity\Repositories\Contracts\EmailVerificationRepositoryInterface;
use App\Domains\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Domains\Identity\Repositories\Eloquent\UserRepository;
// School Domain — Repository & Service contracts (canonical)
use App\Domains\Identity\Services\AuthService;
use App\Domains\Identity\Services\Interface\AuthServiceInterface;
use App\Domains\School\Application\Grades\Commands\CreateGradeCommand;
use App\Domains\School\Application\Grades\Commands\CreateGradeCommandHandler;
use App\Domains\School\Application\Grades\Queries\GetMyGradesQuery;
use App\Domains\School\Application\Grades\Queries\GetMyGradesQueryHandler;
use App\Domains\School\Repositories\Contracts\ConductScoreRepositoryInterface;
use App\Domains\School\Repositories\Contracts\DisciplineRepositoryInterface;
use App\Domains\School\Repositories\Contracts\DisciplineTypeRepositoryInterface;
use App\Domains\School\Repositories\Contracts\GradeRepositoryInterface;
use App\Domains\School\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Domains\School\Repositories\Eloquent\ConductScoreRepository;
use App\Domains\School\Repositories\Eloquent\DisciplineRepository;
use App\Domains\School\Repositories\Eloquent\DisciplineTypeRepository;
use App\Domains\School\Repositories\Eloquent\GradeRepository;
use App\Domains\School\Repositories\Eloquent\ScheduleRepository;
use App\Domains\School\Services\ConductScoreService;
use App\Domains\School\Services\DashBoardService;
use App\Domains\School\Services\DisciplineService;
use App\Domains\School\Services\GradeService;
use App\Domains\School\Services\Interface\ConductScoreServiceInterface;
use App\Domains\School\Services\Interface\DashBoardServiceInterface;
use App\Domains\School\Services\Interface\DisciplineServiceInterface;
use App\Domains\School\Services\Interface\GradeServiceInterface;
use App\Domains\School\Services\Interface\ScheduleServiceInterface;
use App\Domains\School\Services\Interface\StudentServiceInterface;
use App\Domains\School\Services\ScheduleService;
use App\Domains\School\Services\StudentService;
use App\Domains\Shared\Bus\CommandBus;
use App\Domains\Shared\Bus\InMemoryCommandBus;
use App\Domains\Shared\Bus\InMemoryQueryBus;
use App\Domains\Shared\Bus\QueryBus;
use App\Observers\UserObserver;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // === School Domain Bindings (canonical, per-service DB) ===
        $this->app->bind(ScheduleRepositoryInterface::class, ScheduleRepository::class);
        $this->app->bind(GradeRepositoryInterface::class, GradeRepository::class);
        $this->app->bind(DisciplineRepositoryInterface::class, DisciplineRepository::class);
        $this->app->bind(ConductScoreRepositoryInterface::class, ConductScoreRepository::class);
        $this->app->bind(DisciplineTypeRepositoryInterface::class, DisciplineTypeRepository::class);

        $this->app->bind(ScheduleServiceInterface::class, ScheduleService::class);
        $this->app->bind(GradeServiceInterface::class, GradeService::class);
        $this->app->bind(DisciplineServiceInterface::class, DisciplineService::class);
        $this->app->bind(ConductScoreServiceInterface::class, ConductScoreService::class);
        $this->app->bind(StudentServiceInterface::class, StudentService::class);
        $this->app->bind(DashBoardServiceInterface::class, DashBoardService::class);

        // Finance via Go microservice — không bind repository/service trực tiếp
        // Identity — T1.2: re-enable for feature/auth (gateway still proxies in prod)
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(EmailVerificationRepositoryInterface::class, EmailVerificationRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);

        // === CQRS pilot: School grades (Phase 3) ===
        $this->app->singleton(CommandBus::class, function ($app) {
            $bus = new InMemoryCommandBus;
            $bus->register(CreateGradeCommand::class, fn (CreateGradeCommand $command) => $app->make(CreateGradeCommandHandler::class)->handle($command));

            return $bus;
        });
        $this->app->singleton(QueryBus::class, function ($app) {
            $bus = new InMemoryQueryBus;
            $bus->register(GetMyGradesQuery::class, fn (GetMyGradesQuery $query) => $app->make(GetMyGradesQueryHandler::class)->handle($query));

            return $bus;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);

        Scramble::configure()->routes(
            fn ($route) => str_starts_with($route->uri, 'api/')
        );

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
