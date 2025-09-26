<?php

namespace App\Providers;

use App\Repositories\Eloquent\UserRepository;
use App\Services\AuthServices;
use App\Services\DashBoardService;
use App\Services\Interface\DashBoardServiceInterface;
use App\Services\Interface\AuthServicesInterface;
use App\Services\AuthService;
use App\Services\Interface\AuthServiceInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Interface\UserServiceInterface;
use App\Services\UserService;
use Illuminate\Support\ServiceProvider;
use App\Services\Interface\ScheduleServiceInterface;
use App\Services\ScheduleService;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Repositories\Eloquent\ScheduleRepository;
use SebastianBergmann\CodeCoverage\Report\Html\Dashboard;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(DashBoardServiceInterface::class, DashBoardService::class);
        $this->app->bind(ScheduleRepositoryInterface::class, ScheduleRepository::class);
        $this->app->bind(ScheduleServiceInterface::class, ScheduleService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
