<?php

namespace Mdbtq\PageViews;

use Illuminate\Support\ServiceProvider;
use Mdbtq\PageViews\Console\PageViewStatsCommand;
use Mdbtq\PageViews\Console\PurgePageViewsCommand;

class PageViewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/page-views.php', 'page-views');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/page-views.php' => config_path('page-views.php'),
        ], 'page-views-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'page-views-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                PageViewStatsCommand::class,
                PurgePageViewsCommand::class,
            ]);
        }
    }
}
