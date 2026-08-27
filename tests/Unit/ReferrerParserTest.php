<?php

use Mdbtq\PageViews\Support\ReferrerParser;

beforeEach(function () {
    $this->parser = new ReferrerParser;
});

it('reduces a referrer to its bare host', function (?string $referrer, ?string $expected) {
    expect($this->parser->host($referrer))->toBe($expected);
})->with([
    ['https://www.google.com/search?q=laravel', 'google.com'],
    ['https://news.ycombinator.com/item?id=1', 'news.ycombinator.com'],
    ['http://EXAMPLE.COM/path', 'example.com'],
    ['https://example.com', 'example.com'],
    [null, null],
    ['', null],
    ['not a url', null],
    ['/relative/path', null],
]);

it('groups the same host across differing query strings', function () {
    $a = $this->parser->host('https://www.google.com/search?q=one&utm_source=x');
    $b = $this->parser->host('https://google.com/search?q=two');

    expect($a)->toBe($b);
});
