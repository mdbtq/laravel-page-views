<?php

namespace Mdbtq\PageViews\Tests;

use Mdbtq\PageViews\PageViewsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PageViewsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $this->databaseConfig());
    }

    /**
     * The suite runs on SQLite by default; DB_DRIVER switches it to a real
     * MySQL or Postgres instance so the aggregate SQL is exercised on each.
     *
     * @return array<string, mixed>
     */
    private function databaseConfig(): array
    {
        return match (env('DB_DRIVER', 'sqlite')) {
            'mysql' => [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '33306'),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', 'secret'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '55432'),
                'database' => env('DB_DATABASE', 'testing'),
                'username' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', 'secret'),
                'charset' => 'utf8',
                'prefix' => '',
            ],
            default => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        };
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
