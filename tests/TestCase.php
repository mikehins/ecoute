<?php

declare(strict_types=1);

namespace MikeHins\Ecoute\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\ServiceProvider;
use MikeHins\Ecoute\EcouteServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    /**
     * Get package providers for the test environment.
     *
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EcouteServiceProvider::class,
        ];
    }

    /**
     * Configure the test application environment.
     *
     * @param  Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('ecoute.enabled', true);
        $app['config']->set('ecoute.environments', ['testing']);
        $app['config']->set('ecoute.deduplication.enabled', true);
        $app['config']->set('ecoute.deduplication.window_hours', 24);
        $app['config']->set('ecoute.rate_limit.attempts', 100);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    /**
     * Create a new authenticated user for use in tests.
     */
    protected function createUser(): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);
        $user->save();

        return $user;
    }

    /**
     * Run the package migrations for the in-memory test database.
     */
    private function setUpDatabase(): void
    {
        $this->loadLaravelMigrations();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
