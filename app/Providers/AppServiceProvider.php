<?php

namespace App\Providers;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use App\Domains\Identity\Models\User;
use App\Observers\UserObserver;
use Dedoc\Scramble\Scramble;

// School Domain — Repository & Service contracts (canonical)
use App\Domains\School\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Domains\School\Repositories\Contracts\GradeRepositoryInterface;
use App\Domains\School\Repositories\Contracts\DisciplineRepositoryInterface;
use App\Domains\School\Repositories\Contracts\ConductScoreRepositoryInterface;
use App\Domains\School\Repositories\Contracts\DisciplineTypeRepositoryInterface;
use App\Domains\School\Repositories\Eloquent\ScheduleRepository;
use App\Domains\School\Repositories\Eloquent\GradeRepository;
use App\Domains\School\Repositories\Eloquent\DisciplineRepository;
use App\Domains\School\Repositories\Eloquent\ConductScoreRepository;
use App\Domains\School\Repositories\Eloquent\DisciplineTypeRepository;
use App\Domains\School\Services\Interface\ScheduleServiceInterface;
use App\Domains\School\Services\Interface\GradeServiceInterface;
use App\Domains\School\Services\Interface\DisciplineServiceInterface;
use App\Domains\School\Services\Interface\ConductScoreServiceInterface;
use App\Domains\School\Services\Interface\StudentServiceInterface;
use App\Domains\School\Services\Interface\DashBoardServiceInterface;
use App\Domains\School\Services\ScheduleService;
use App\Domains\School\Services\GradeService;
use App\Domains\School\Services\DisciplineService;
use App\Domains\School\Services\ConductScoreService;
use App\Domains\School\Services\StudentService;
use App\Domains\School\Services\DashBoardService;

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
        // Identity đã tách microservice — không bind repository/service tại monolith
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
