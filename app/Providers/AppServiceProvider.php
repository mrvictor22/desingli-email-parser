<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SesMapperService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         $this->app->singleton(SesMapperService::class, function ($app) {
        return new SesMapperService();
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
