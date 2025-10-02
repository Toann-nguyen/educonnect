<?php

namespace App\Providers;

use App\Repositories\Contracts\FeeTypeRepositoryInterface;
use App\Repositories\Contracts\GradeRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Eloquent\GradeRepository;
use App\Repositories\Eloquent\InvoiceRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Services\AuthServices;
use App\Services\DashBoardService;
use App\Services\FeeTypeService;
use App\Services\GradeService;
use App\Services\Interface\DashBoardServiceInterface;
use App\Services\Interface\AuthServicesInterface;
use App\Services\AuthService;
use App\Services\Interface\AuthServiceInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Interface\FeeTypeServiceInterface;
use App\Services\Interface\GradeServiceInterface;
use App\Services\Interface\InvoiceServiceInterface;
use App\Services\Interface\PaymentServiceInterface;
use App\Services\Interface\StudentServiceInterface;
use App\Services\Interface\UserServiceInterface;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\StudentService;
use App\Services\UserService;
use Illuminate\Cache\Repository;
use Illuminate\Support\ServiceProvider;
use App\Services\Interface\ScheduleServiceInterface;
use App\Services\ScheduleService;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Repositories\Eloquent\ScheduleRepository;
use SebastianBergmann\CodeCoverage\Report\Html\Dashboard;
use \App\Repositories\Eloquent\FeeTypeRepository;
use App\Services\Interface\DisciplineServiceInterface;
use App\Services\DisciplineService;
use App\Services\Interface\ConductScoreServiceInterface;
use App\Services\ConductScoreService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // bind Repository
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
        $this->app->bind(ScheduleRepositoryInterface::class, ScheduleRepository::class);
        $this->app->bind(GradeRepositoryInterface::class, GradeRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(FeeTypeRepositoryInterface::class, FeeTypeRepository::class);

        $this->app->bind(DisciplineServiceInterface::class, DisciplineService::class);
        $this->app->bind(ConductScoreServiceInterface::class, ConductScoreService::class);
        //bind service
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(DashBoardServiceInterface::class, DashBoardService::class);
        $this->app->bind(ScheduleServiceInterface::class, ScheduleService::class);
        $this->app->bind(StudentServiceInterface::class, StudentService::class);
        $this->app->bind(InvoiceServiceInterface::class, InvoiceService::class);
        $this->app->bind(GradeServiceInterface::class, GradeService::class);
        $this->app->bind(PaymentServiceInterface::class, PaymentService::class);
        $this->app->bind(FeeTypeServiceInterface::class, FeeTypeService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
