<?php

namespace EscolaLms\Reports\Tests;

use EscolaLms\Categories\EscolaLmsCategoriesServiceProvider;
use EscolaLms\Core\Tests\TestCase as CoreTestCase;
use EscolaLms\Courses\EscolaLmsCourseServiceProvider;
use EscolaLms\Payments\Providers\PaymentsServiceProvider;
use EscolaLms\Reports\Database\Seeders\ReportsPermissionSeeder;
use EscolaLms\Reports\EscolaLmsReportsServiceProvider;
use EscolaLms\Reports\Tests\Models\Client;
use EscolaLms\Reports\Tests\Models\TestUser;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

class TestCase extends CoreTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Passport::useClientModel(Client::class);
        $this->seed(ReportsPermissionSeeder::class);
    }

    protected function getPackageProviders($app)
    {
        return [
            ...parent::getPackageProviders($app),
            PermissionServiceProvider::class,
            PassportServiceProvider::class,
            EscolaLmsCategoriesServiceProvider::class,
            EscolaLmsReportsServiceProvider::class,
            EscolaLmsCourseServiceProvider::class,
            PaymentsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('auth.providers.users.model', TestUser::class);
        $app['config']->set('passport.client_uuids', true);
    }
}
