<?php

use Mdbtq\PageViews\Support\LogLineParser;

beforeEach(function () {
    $this->parser = new LogLineParser;
});

it('parses an NCSA combined log line', function () {
    $line = '203.0.113.5 - - [10/Oct/2024:13:55:36 +0200] "GET /about HTTP/1.1" 200 2326 '
        .'"https://example.com/" "Mozilla/5.0 (Macintosh)"';

    expect($this->parser->parse($line))->toMatchArray([
        'ip' => '203.0.113.5',
        'method' => 'GET',
        'target' => '/about',
        'status' => 200,
        'referer' => 'https://example.com/',
        'agent' => 'Mozilla/5.0 (Macintosh)',
    ]);
});

it('treats a dash referer as absent', function () {
    $line = '203.0.113.5 - - [10/Oct/2024:13:55:36 +0200] "GET / HTTP/1.1" 200 12 "-" "Mozilla/5.0"';

    expect($this->parser->parse($line)['referer'])->toBeNull();
});

it('parses a common log line without referer and agent', function () {
    $line = '203.0.113.5 - - [10/Oct/2024:13:55:36 +0200] "GET /plain HTTP/1.1" 200 12';

    expect($this->parser->parse($line))->toMatchArray([
        'target' => '/plain',
        'status' => 200,
        'referer' => null,
        'agent' => '',
    ]);
});

it('parses a JSON log line using nginx field names', function () {
    $line = json_encode([
        'remote_addr' => '203.0.113.9',
        'time_local' => '2024-10-10T13:55:36+02:00',
        'request_method' => 'GET',
        'request_uri' => '/contact',
        'status' => 200,
        'http_referer' => 'https://example.com/',
        'http_user_agent' => 'Mozilla/5.0',
    ]);

    expect($this->parser->parse($line))->toMatchArray([
        'ip' => '203.0.113.9',
        'target' => '/contact',
        'status' => 200,
        'agent' => 'Mozilla/5.0',
    ]);
});

it('parses a JSON log line using cloudflare field names', function () {
    $line = json_encode([
        'ClientIP' => '203.0.113.9',
        'EdgeStartTimestamp' => '2024-10-10T11:55:36Z',
        'ClientRequestMethod' => 'get',
        'ClientRequestURI' => '/pricing',
        'EdgeResponseStatus' => 200,
        'ClientRequestUserAgent' => 'Mozilla/5.0',
    ]);

    expect($this->parser->parse($line))->toMatchArray([
        'method' => 'GET',
        'target' => '/pricing',
        'status' => 200,
    ]);
});

it('returns null for blank and unparsable lines', function (string $line) {
    expect($this->parser->parse($line))->toBeNull();
})->with([
    '',
    '   ',
    'this is not a log line',
    '{"broken": ',
    '{"remote_addr": "203.0.113.9"}',
]);
