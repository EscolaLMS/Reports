<?php

namespace EscolaLms\Reports;

use EscolaLms\Reports\Providers\AuthServiceProvider;
use EscolaLms\Reports\Providers\ScheduleServiceProvider;
use EscolaLms\Reports\Services\Contracts\StatsServiceContract;
use EscolaLms\Reports\Services\StatsService;
use Illuminate\Support\ServiceProvider;

/**
 * SWAGGER_VERSION
 */
class EscolaLmsReportsServiceProvider extends ServiceProvider
{
    public $singletons = [
        StatsServiceContract::class => StatsService::class
    ];

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config.php',
            'reports'
        );
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');

        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        $this->app->register(AuthServiceProvider::class);
        $this->app->register(ScheduleServiceProvider::class);
    }

    public function bootForConsole()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/config.php' => config_path('reports.php'),
        ], 'reports.config');
    }
}
