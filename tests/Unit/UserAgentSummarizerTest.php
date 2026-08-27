<?php

use Mdbtq\PageViews\Support\UserAgentSummarizer;

beforeEach(function () {
    $this->summarizer = new UserAgentSummarizer;
});

it('identifies the browser', function (string $userAgent, ?string $expected) {
    expect($this->summarizer->browser($userAgent))->toBe($expected);
})->with([
    ['Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36', 'Chrome'],
    ['Mozilla/5.0 (Windows NT 10.0) Gecko/20100101 Firefox/121.0', 'Firefox'],
    ['Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15', 'Safari'],
    ['Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36 Edg/120.0', 'Edge'],
    ['Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36 OPR/106.0', 'Opera'],
    ['', null],
    ['something entirely unknown', null],
]);

it('prefers derivatives over the engine they are built on', function () {
    // Edge and Opera both advertise Chrome; the more specific label wins.
    $edge = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36 Edg/120.0';

    expect($this->summarizer->browser($edge))->toBe('Edge');
});

it('identifies the platform', function (string $userAgent, ?string $expected) {
    expect($this->summarizer->platform($userAgent))->toBe($expected);
})->with([
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'macOS'],
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows'],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', 'iOS'],
    ['Mozilla/5.0 (Linux; Android 14; Pixel 8)', 'Android'],
    ['Mozilla/5.0 (X11; Linux x86_64)', 'Linux'],
    ['', null],
]);

it('reduces the user agent to a low-cardinality label', function () {
    // The point of the summary is that version numbers and device details,
    // which is what makes a raw UA identifying, do not survive it.
    $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0.6099.109';

    expect($this->summarizer->browser($userAgent))->toBe('Chrome')
        ->and($this->summarizer->platform($userAgent))->toBe('macOS');
});
