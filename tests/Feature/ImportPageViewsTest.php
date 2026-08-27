<?php

use Mdbtq\PageViews\Models\PageView;

function writeLog(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'pageviews-log-');

    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
}

function combinedLine(string $path = '/about', int $status = 200, string $agent = 'Mozilla/5.0', string $time = '10/Oct/2024:13:55:36 +0000'): string
{
    return "203.0.113.5 - - [{$time}] \"GET {$path} HTTP/1.1\" {$status} 2326 \"-\" \"{$agent}\"";
}

it('imports page views from a combined access log', function () {
    $log = writeLog([combinedLine('/about'), combinedLine('/contact')]);

    $this->artisan("pageviews:import {$log}")->assertSuccessful();

    expect(PageView::query()->pluck('path')->all())->toEqualCanonicalizing(['/about', '/contact']);
});

it('anonymizes the ip and derives a visitor hash for imported rows', function () {
    $log = writeLog([combinedLine()]);

    $this->artisan("pageviews:import {$log}")->assertSuccessful();

    $view = PageView::query()->sole();

    expect($view->ip_anon)->toBe('203.0.113.0')
        ->and($view->visitor_hash)->toHaveLength(64);
});

it('uses the salt of the day the request was served', function () {
    $log = writeLog([
        combinedLine(time: '10/Oct/2024:13:55:36 +0000'),
        combinedLine(time: '11/Oct/2024:13:55:36 +0000'),
    ]);

    $this->artisan("pageviews:import {$log}")->assertSuccessful();

    // Same visitor on two days must not share a hash, or the daily rotation
    // would be defeated for imported traffic.
    expect(PageView::query()->distinct()->count('visitor_hash'))->toBe(2);
});

it('records the timestamp from the log rather than the import time', function () {
    $log = writeLog([combinedLine(time: '10/Oct/2024:13:55:36 +0000')]);

    $this->artisan("pageviews:import {$log}")->assertSuccessful();

    expect(PageView::query()->sole()->viewed_at->toDateString())->toBe('2024-10-10');
});

it('applies bot filtering to imported entries', function () {
    $log = writeLog([
        combinedLine(agent: 'Mozilla/5.0'),
        combinedLine(agent: 'Googlebot/2.1'),
    ]);

    $this->artisan("pageviews:import {$log}")->assertSuccessful();

    expect(PageView::query()->count())->toBe(1);
});

it('skips non-trackable status codes and excluded paths', function () {
    $log = writeLog([
        combinedLine('/about', 200),
        combinedLine('/missing', 404),
        combinedLine('/build/app.css', 200),
    ]);

    $this->artisan("pageviews:import {$log}")->assertSuccessful();

    expect(PageView::query()->pluck('path')->all())->toBe(['/about']);
});

it('honours the --since cutoff', function () {
    $log = writeLog([
        combinedLine(time: '10/Oct/2024:13:55:36 +0000'),
        combinedLine(time: '20/Oct/2024:13:55:36 +0000'),
    ]);

    $this->artisan("pageviews:import {$log} --since=2024-10-15")->assertSuccessful();

    expect(PageView::query()->count())->toBe(1);
});

it('writes nothing on a dry run', function () {
    $log = writeLog([combinedLine()]);

    $this->artisan("pageviews:import {$log} --dry-run")->assertSuccessful();

    expect(PageView::query()->count())->toBe(0);
});

it('fails when the log file cannot be read', function () {
    $this->artisan('pageviews:import /nonexistent/access.log')->assertFailed();
});

it('ignores unparsable lines without failing', function () {
    $log = writeLog(['garbage', combinedLine(), '']);

    $this->artisan("pageviews:import {$log}")->assertSuccessful();

    expect(PageView::query()->count())->toBe(1);
});

it('skips entries whose timestamp cannot be parsed', function () {
    $log = writeLog([
        '203.0.113.5 - - [not-a-date] "GET /about HTTP/1.1" 200 2326 "-" "Mozilla/5.0"',
        combinedLine(),
    ]);

    $this->artisan("pageviews:import {$log}")->assertSuccessful();

    expect(PageView::query()->count())->toBe(1);
});
