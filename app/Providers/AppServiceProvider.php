<?php

namespace App\Providers;

use App\Repositories\Contracts\ConductScoreRepositoryInterface;
use App\Repositories\Contracts\DisciplineRepositoryInterface;
use App\Repositories\Contracts\DisciplineTypeRepositoryInterface;
use App\Repositories\Contracts\FeeTypeRepositoryInterface;
use App\Repositories\Contracts\GradeRepositoryInterface;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RolePermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Eloquent\ConductScoreRepository;
use App\Repositories\Eloquent\DisciplineRepository;
use App\Repositories\Eloquent\GradeRepository;
use App\Repositories\Eloquent\InvoiceRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\PermissionRepository;
use App\Repositories\Eloquent\RolePermissionRepository;
use App\Repositories\Eloquent\RoleRepository;
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
use App\Services\Interface\PermissionServiceInterface;
use App\Services\Interface\StudentServiceInterface;
use App\Services\Interface\UserServiceInterface;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\RolePermissionService;
use App\Services\StudentService;
use App\Services\UserRoleService;
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
use App\Services\RoleService;
use App\Services\PermissionService;

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
        $this->app->bind(DisciplineRepositoryInterface::class, DisciplineRepository::class);
        $this->app->bind(DisciplineTypeRepositoryInterface::class, DisciplineRepository::class);
        $this->app->bind(ConductScoreRepositoryInterface::class, ConductScoreRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
        $this->app->bind(PermissionRepositoryInterface::class, PermissionRepository::class);
        $this->app->bind(RolePermissionRepositoryInterface::class, RolePermissionRepository::class);
       
        //bind service
        $this->app->bind(ConductScoreServiceInterface::class, ConductScoreService::class);
        $this->app->bind(PermissionServiceInterface::class, PermissionService::class);
        $this->app->bind(DisciplineServiceInterface::class, DisciplineService::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(DashBoardServiceInterface::class, DashBoardService::class);
        $this->app->bind(ScheduleServiceInterface::class, ScheduleService::class);
        $this->app->bind(StudentServiceInterface::class, StudentService::class);
        $this->app->bind(InvoiceServiceInterface::class, InvoiceService::class);
        $this->app->bind(GradeServiceInterface::class, GradeService::class);
        $this->app->bind(PaymentServiceInterface::class, PaymentService::class);
        $this->app->bind(FeeTypeServiceInterface::class, FeeTypeService::class);
       // Role Service with dependencies
        $this->app->bind(\App\Services\Interface\RoleServiceInterface::class, function ($app) {
            return new \App\Services\RoleService(
                $app->make(\App\Repositories\Contracts\RoleRepositoryInterface::class),
                $app->make(\App\Repositories\Contracts\PermissionRepositoryInterface::class),
                $app->make(\App\Repositories\Contracts\RolePermissionRepositoryInterface::class)
            );
        });
        
        // UserRole Service with dependencies
        $this->app->bind(\App\Services\Interface\UserRoleServiceInterface::class, function ($app) {
            return new \App\Services\UserRoleService(
                $app->make(\App\Repositories\Contracts\RolePermissionRepositoryInterface::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
