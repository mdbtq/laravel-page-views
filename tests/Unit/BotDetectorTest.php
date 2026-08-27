<?php

use Mdbtq\PageViews\Support\BotDetector;

beforeEach(function () {
    $this->detector = app(BotDetector::class);
});

it('detects known bot signatures regardless of casing', function (string $userAgent) {
    expect($this->detector->isBot($userAgent))->toBeTrue();
})->with([
    'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    'Mozilla/5.0 (compatible; bingbot/2.0)',
    'curl/8.4.0',
    'python-requests/2.31.0',
    'Chrome-Lighthouse',
    'Mozilla/5.0 HeadlessChrome/120.0.0.0',
    'Java/17.0.1',
]);

it('allows genuine browser user agents', function (string $userAgent) {
    expect($this->detector->isBot($userAgent))->toBeFalse();
})->with([
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 Version/17.2 Mobile/15E148 Safari/604.1',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
]);

it('treats an empty user agent as a bot by default', function (string $userAgent) {
    expect($this->detector->isBot($userAgent))->toBeTrue();
})->with(['', '   ']);

it('can be configured to accept an empty user agent', function () {
    config()->set('page-views.empty_user_agent_is_bot', false);

    expect($this->detector->isBot(''))->toBeFalse();
});

it('honours custom signatures', function () {
    config()->set('page-views.bot_signatures', ['my-scraper']);

    expect($this->detector->isBot('My-Scraper/1.0'))->toBeTrue()
        ->and($this->detector->isBot('Googlebot'))->toBeFalse();
});
