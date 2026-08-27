<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Mdbtq\PageViews\PageViewRecorder;
use Mdbtq\PageViews\PageViewsServiceProvider;
use Mdbtq\PageViews\Support\IpAnonymizer;

it('creates the package tables', function () {
    expect(Schema::hasTable('page_views'))->toBeTrue()
        ->and(Schema::hasTable('page_view_daily'))->toBeTrue();
});

it('creates every tracked column', function () {
    expect(Schema::hasColumns('page_views', [
        'path', 'route', 'referrer', 'referrer_host',
        'utm_source', 'utm_medium', 'utm_campaign',
        'browser', 'platform', 'ip_anon', 'visitor_hash', 'country', 'viewed_at',
    ]))->toBeTrue();
});

it('ships a migration whose filename sorts as a timestamped migration', function () {
    $files = glob(__DIR__.'/../../database/migrations/*.php');

    expect($files)->toHaveCount(1)
        ->and(basename($files[0]))->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_page_views_table\.php$/');
});

it('does not load its own migration once one has been published', function () {
    // Guards the duplicate CREATE TABLE that publishing plus loading caused.
    $provider = new PageViewsServiceProvider($this->app);
    $method = new ReflectionMethod($provider, 'migrationsArePublished');

    expect($method->invoke($provider))->toBeFalse();

    $published = database_path('migrations/2026_01_01_000000_create_page_views_table.php');
    @mkdir(dirname($published), 0777, true);
    file_put_contents($published, '<?php');

    try {
        expect($method->invoke($provider))->toBeTrue();
    } finally {
        @unlink($published);
    }
});

it('resolves its services as singletons', function () {
    expect(app(IpAnonymizer::class))->toBe(app(IpAnonymizer::class))
        ->and(app(PageViewRecorder::class))->toBe(app(PageViewRecorder::class));
});

it('registers the console commands', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)->toContain('pageviews:stats', 'pageviews:purge', 'pageviews:rollup');
});
