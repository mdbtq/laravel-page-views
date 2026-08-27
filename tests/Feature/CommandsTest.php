<?php

use Mdbtq\PageViews\Models\PageView;
use Mdbtq\PageViews\Models\PageViewDaily;

function makeView(array $attributes = []): PageView
{
    return PageView::create(array_merge([
        'path' => '/about',
        'ip_anon' => '203.0.113.0',
        'visitor_hash' => str_repeat('a', 64),
        'viewed_at' => now(),
    ], $attributes));
}

it('reports totals and unique visitors', function () {
    makeView(['visitor_hash' => str_repeat('a', 64)]);
    makeView(['visitor_hash' => str_repeat('a', 64)]);
    makeView(['visitor_hash' => str_repeat('b', 64)]);

    $this->artisan('pageviews:stats --json')
        ->assertSuccessful();

    $output = json_decode(artisanOutput('pageviews:stats --json'), true);

    expect($output['total_views'])->toBe(3)
        ->and($output['unique_visitors'])->toBe(2);
});

it('shows every day in the window regardless of --top', function () {
    foreach (range(0, 9) as $daysAgo) {
        makeView(['viewed_at' => now()->subDays($daysAgo)]);
    }

    $output = json_decode(artisanOutput('pageviews:stats --days=10 --top=2 --json'), true);

    // --top limits the ranked breakdowns, not the daily series.
    expect($output['daily'])->toHaveCount(10)
        ->and($output['top_pages'])->toHaveCount(1);
});

it('limits ranked breakdowns to --top entries', function () {
    foreach (['/a', '/b', '/c', '/d'] as $path) {
        makeView(['path' => $path]);
    }

    $output = json_decode(artisanOutput('pageviews:stats --top=2 --json'), true);

    expect($output['top_pages'])->toHaveCount(2);
});

it('groups referrers by host', function () {
    makeView(['referrer' => 'https://google.com/a', 'referrer_host' => 'google.com']);
    makeView(['referrer' => 'https://google.com/b?utm=1', 'referrer_host' => 'google.com']);

    $output = json_decode(artisanOutput('pageviews:stats --json'), true);

    expect($output['top_referrers'])->toHaveCount(1)
        ->and($output['top_referrers'][0]['views'])->toBe(2);
});

it('purges records older than the retention window', function () {
    makeView(['viewed_at' => now()->subDays(100)]);
    makeView(['viewed_at' => now()->subDays(10)]);

    $this->artisan('pageviews:purge --days=90')
        ->expectsOutputToContain('Purged 1 page view records')
        ->assertSuccessful();

    expect(PageView::count())->toBe(1);
});

it('reports without deleting on a dry run', function () {
    makeView(['viewed_at' => now()->subDays(100)]);

    $this->artisan('pageviews:purge --days=90 --dry-run')
        ->expectsOutputToContain('Would purge 1')
        ->assertSuccessful();

    expect(PageView::count())->toBe(1);
});

it('purges in chunks without losing records', function () {
    foreach (range(1, 25) as $i) {
        makeView(['viewed_at' => now()->subDays(100)]);
    }
    makeView(['viewed_at' => now()]);

    $this->artisan('pageviews:purge --days=90 --chunk=10')->assertSuccessful();

    expect(PageView::count())->toBe(1);
});

it('rejects a non-positive retention window', function () {
    $this->artisan('pageviews:purge --days=0')->assertFailed();
});

it('aggregates raw views into daily totals', function () {
    makeView(['path' => '/about', 'visitor_hash' => str_repeat('a', 64)]);
    makeView(['path' => '/about', 'visitor_hash' => str_repeat('a', 64)]);
    makeView(['path' => '/about', 'visitor_hash' => str_repeat('b', 64)]);
    makeView(['path' => '/contact', 'visitor_hash' => str_repeat('b', 64)]);

    $this->artisan('pageviews:rollup --days=1')->assertSuccessful();

    $about = PageViewDaily::where('path', '/about')->first();

    expect(PageViewDaily::count())->toBe(2)
        ->and($about->views)->toBe(3)
        ->and($about->visitors)->toBe(2);
});

it('is safe to run the rollup twice', function () {
    makeView();

    $this->artisan('pageviews:rollup --days=1')->assertSuccessful();
    $this->artisan('pageviews:rollup --days=1')->assertSuccessful();

    expect(PageViewDaily::count())->toBe(1)
        ->and(PageViewDaily::first()->views)->toBe(1);
});

it('keeps aggregated history after the raw rows are purged', function () {
    makeView(['viewed_at' => now()->subDays(100)]);

    $this->artisan('pageviews:rollup --days=101')->assertSuccessful();
    $this->artisan('pageviews:purge --days=90')->assertSuccessful();

    expect(PageView::count())->toBe(0)
        ->and(PageViewDaily::count())->toBe(1);
});

it('counts visitors across rows recorded before hashing was enabled', function () {
    // Legacy rows carry no visitor_hash and fall back to the anonymized IP.
    makeView(['visitor_hash' => null, 'ip_anon' => '203.0.113.0']);
    makeView(['visitor_hash' => null, 'ip_anon' => '203.0.113.0']);
    makeView(['visitor_hash' => null, 'ip_anon' => '198.51.100.0']);
    makeView(['visitor_hash' => str_repeat('c', 64)]);

    $output = json_decode(artisanOutput('pageviews:stats --json'), true);

    expect($output['total_views'])->toBe(4)
        ->and($output['unique_visitors'])->toBe(3);
});
