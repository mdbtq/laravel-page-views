<?php

namespace Mdbtq\PageViews;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\ServiceProvider;
use Mdbtq\PageViews\Console\PageViewStatsCommand;
use Mdbtq\PageViews\Console\PurgePageViewsCommand;
use Mdbtq\PageViews\Console\RollupPageViewsCommand;
use Mdbtq\PageViews\Support\BotDetector;
use Mdbtq\PageViews\Support\CountryResolver;
use Mdbtq\PageViews\Support\IpAnonymizer;
use Mdbtq\PageViews\Support\ReferrerParser;
use Mdbtq\PageViews\Support\VisitorHasher;

class PageViewsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/page-views.php', 'page-views');

        $this->app->singleton(IpAnonymizer::class);
        $this->app->singleton(ReferrerParser::class);
        $this->app->singleton(CountryResolver::class);
        $this->app->singleton(BotDetector::class);
        $this->app->singleton(VisitorHasher::class);
        $this->app->singleton(PageViewRecorder::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/page-views.php' => config_path('page-views.php'),
        ], 'page-views-config');

        // The migration is loaded from the package itself; publishing it is an
        // opt-in escape hatch for applications that want to edit the schema.
        // Loading and publishing at once would run the same migration twice.
        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'page-views-migrations');

        if (! $this->migrationsArePublished()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                PageViewStatsCommand::class,
                PurgePageViewsCommand::class,
                RollupPageViewsCommand::class,
            ]);

            $this->scheduleCommands();
        }
    }

    /**
     * Detect a published copy of the migration so the package stops loading
     * its own, avoiding a duplicate CREATE TABLE.
     */
    private function migrationsArePublished(): bool
    {
        $published = glob(database_path('migrations/*_create_page_views_table.php'));

        return is_array($published) && $published !== [];
    }

    private function scheduleCommands(): void
    {
        $this->app->booted(function (): void {
            /** @var Config $config */
            $config = $this->app->make(Config::class);
            $schedule = $this->app->make(Schedule::class);

            if ($config->get('page-views.schedule.rollup.enabled', false)) {
                $schedule->command(RollupPageViewsCommand::class)
                    ->dailyAt((string) $config->get('page-views.schedule.rollup.at', '02:00'));
            }

            if ($config->get('page-views.schedule.purge.enabled', false)) {
                $schedule->command(PurgePageViewsCommand::class)
                    ->dailyAt((string) $config->get('page-views.schedule.purge.at', '03:00'));
            }
        });
    }
}
