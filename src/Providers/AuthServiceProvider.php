<?php

namespace EscolaLms\Reports\Providers;

use EscolaLms\Reports\Models\Measurement;
use EscolaLms\Reports\Models\Report;
use EscolaLms\Reports\Policies\MeasurementPolicy;
use EscolaLms\Reports\Policies\ReportPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Measurement::class => MeasurementPolicy::class,
        Report::class => ReportPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        if (!$this->app->routesAreCached()) {
            Passport::routes();
        }
    }
}
