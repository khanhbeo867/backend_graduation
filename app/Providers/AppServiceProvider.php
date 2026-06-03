<?php

namespace App\Providers;

use App\Http\Interfaces\EmployeeServiceInterface;
use App\Http\Interfaces\UserServiceInterface;
use App\Http\Services\EmployeeService;
use App\Http\Services\UserService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //register service
        $this->app->singleton(UserServiceInterface::class, UserService::class);
        $this->app->singleton(EmployeeServiceInterface::class, EmployeeService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
