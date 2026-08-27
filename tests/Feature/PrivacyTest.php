<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mdbtq\PageViews\Middleware\TrackPageView;
use Mdbtq\PageViews\Models\PageView;

beforeEach(function () {
    Route::middleware(['web', TrackPageView::class])->get('/about', fn () => 'ok');
});

it('does not store the raw user agent', function () {
    $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0.6099.109';

    $this->withHeader('User-Agent', $userAgent)->get('/about')->assertOk();

    $row = (array) DB::table('page_views')->sole();

    // The raw header is what makes the visitor hash reproducible from the
    // table, so no column may contain it or any part that identifies a build.
    expect($row)->not->toContain($userAgent)
        ->and(implode('|', array_map(strval(...), array_filter($row))))
        ->not->toContain('120.0.6099.109');
});

it('stores a coarse browser and platform instead', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0.0.0')
        ->get('/about')->assertOk();

    $view = PageView::query()->sole();

    expect($view->browser)->toBe('Chrome')
        ->and($view->platform)->toBe('macOS');
});

it('cannot reproduce the visitor hash from the stored row alone', function () {
    $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0.0.0';

    $this->withHeader('User-Agent', $userAgent)->get('/about')->assertOk();

    $view = PageView::query()->sole();

    // Recomputing the hash needs the raw UA and the full IP. With only the
    // stored, anonymized values the result must not match.
    $fromStoredRow = hash('sha256', implode('|', [
        now()->toDateString(),
        config('app.key'),
        $view->ip_anon,
        $view->browser.' '.$view->platform,
    ]));

    expect($view->visitor_hash)->not->toBe($fromStoredRow);
});
