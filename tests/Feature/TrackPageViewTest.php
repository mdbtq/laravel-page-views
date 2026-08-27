<?php

use Illuminate\Support\Facades\Route;
use Mdbtq\PageViews\Middleware\TrackPageView;
use Mdbtq\PageViews\Models\PageView;

beforeEach(function () {
    Route::middleware(TrackPageView::class)->group(function () {
        Route::get('/about', fn () => 'about')->name('pages.about');
        Route::get('/blog/{slug}', fn () => 'post')->name('blog.show');
        Route::post('/contact', fn () => 'ok');
        Route::get('/missing', fn () => response('gone', 404));
        Route::get('/cached', fn () => response('', 304));
        Route::get('/build/app.css', fn () => 'css');
    });
});

it('records a page view for a successful GET request', function () {
    $this->get('/about');

    expect(PageView::count())->toBe(1);

    $view = PageView::first();
    expect($view->path)->toBe('/about')
        ->and($view->route)->toBe('pages.about')
        ->and($view->viewed_at)->not->toBeNull();
});

it('ignores non-GET requests', function () {
    $this->post('/contact');

    expect(PageView::count())->toBe(0);
});

it('ignores unsuccessful responses', function () {
    $this->get('/missing');

    expect(PageView::count())->toBe(0);
});

it('records a 304 response because a cached page is still a visit', function () {
    $this->get('/cached');

    expect(PageView::count())->toBe(1);
});

it('respects the configured trackable status codes', function () {
    config()->set('page-views.trackable_status_codes', [200]);

    $this->get('/cached');

    expect(PageView::count())->toBe(0);
});

it('skips excluded paths', function () {
    $this->get('/build/app.css');

    expect(PageView::count())->toBe(0);
});

it('skips bot traffic', function () {
    $this->get('/about', ['User-Agent' => 'Googlebot/2.1']);

    expect(PageView::count())->toBe(0);
});

it('honours the Do Not Track header', function (string $header) {
    $this->get('/about', [$header => '1', 'User-Agent' => 'Chrome']);

    expect(PageView::count())->toBe(0);
})->with(['DNT', 'Sec-GPC']);

it('ignores Do Not Track when configured to', function () {
    config()->set('page-views.respect_do_not_track', false);

    $this->get('/about', ['DNT' => '1', 'User-Agent' => 'Chrome']);

    expect(PageView::count())->toBe(1);
});

it('records nothing when the package is disabled', function () {
    config()->set('page-views.enabled', false);

    $this->get('/about');

    expect(PageView::count())->toBe(0);
});

it('stores an anonymized ip rather than the raw address', function () {
    $this->call('GET', '/about', server: ['REMOTE_ADDR' => '203.0.113.42']);

    expect(PageView::first()->ip_anon)->toBe('203.0.113.0');
});

it('captures the referrer host separately from the full referrer', function () {
    $this->get('/about', ['referer' => 'https://www.google.com/search?q=laravel']);

    $view = PageView::first();

    expect($view->referrer)->toBe('https://www.google.com/search?q=laravel')
        ->and($view->referrer_host)->toBe('google.com');
});

it('captures utm parameters and discards the rest of the query string', function () {
    $this->get('/about?utm_source=newsletter&utm_medium=email&utm_campaign=launch&secret=abc');

    $view = PageView::first();

    expect($view->utm_source)->toBe('newsletter')
        ->and($view->utm_medium)->toBe('email')
        ->and($view->utm_campaign)->toBe('launch')
        ->and($view->path)->toBe('/about')
        ->and($view->path)->not->toContain('secret');
});

it('does not capture utm parameters when disabled', function () {
    config()->set('page-views.track_utm', false);

    $this->get('/about?utm_source=newsletter');

    expect(PageView::first()->utm_source)->toBeNull();
});

it('assigns the same visitor hash to repeat visits from one visitor', function () {
    $this->call('GET', '/about', server: ['REMOTE_ADDR' => '203.0.113.42', 'HTTP_USER_AGENT' => 'Chrome']);
    $this->call('GET', '/blog/hello', server: ['REMOTE_ADDR' => '203.0.113.42', 'HTTP_USER_AGENT' => 'Chrome']);

    expect(PageView::count())->toBe(2)
        ->and(PageView::distinct()->count('visitor_hash'))->toBe(1);
});

it('never lets a tracking failure break the response', function () {
    config()->set('page-views.excluded_paths', '#[unclosed#');

    $response = $this->get('/about');

    $response->assertOk();
});
