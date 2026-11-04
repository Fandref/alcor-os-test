<?php

namespace App\Providers;

use App\Domain\Payroll\Repositories\PayrollLineRepository;
use App\Infrastructure\Persistence\Eloquent\EloquentPayrollLineRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PayrollLineRepository::class, EloquentPayrollLineRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
