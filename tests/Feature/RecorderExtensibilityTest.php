<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Mdbtq\PageViews\Events\PageViewRecorded;
use Mdbtq\PageViews\Jobs\RecordPageView;
use Mdbtq\PageViews\Middleware\TrackPageView;
use Mdbtq\PageViews\Models\PageView;
use Mdbtq\PageViews\PageViewRecorder;

beforeEach(function () {
    Route::middleware(TrackPageView::class)->group(function () {
        Route::get('/about', fn () => 'about');
        Route::get('/private', fn () => 'private');
    });
});

it('dispatches an event when a view is recorded', function () {
    Event::fake([PageViewRecorded::class]);

    $this->get('/about');

    Event::assertDispatched(PageViewRecorded::class, fn ($event) => $event->pageView->path === '/about');
});

it('queues the write when the queue driver is selected', function () {
    Queue::fake();
    config()->set('page-views.driver', 'queue');

    $this->get('/about');

    Queue::assertPushed(RecordPageView::class);
    expect(PageView::count())->toBe(0);
});

it('writes the record when the queued job runs', function () {
    config()->set('page-views.driver', 'queue');

    $this->get('/about');

    expect(PageView::count())->toBe(1)
        ->and(PageView::first()->path)->toBe('/about');
});

it('lets the application veto a page view', function () {
    app(PageViewRecorder::class)->trackWhen(
        fn ($request) => ! $request->is('private')
    );

    $this->get('/private');
    $this->get('/about');

    expect(PageView::count())->toBe(1)
        ->and(PageView::first()->path)->toBe('/about');
});
