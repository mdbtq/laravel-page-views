<?php

use Mdbtq\PageViews\Support\IpAnonymizer;

beforeEach(function () {
    $this->anonymizer = new IpAnonymizer;
});

it('zeroes the last octet of an IPv4 address', function (string $input, string $expected) {
    expect($this->anonymizer->anonymize($input))->toBe($expected);
})->with([
    ['192.168.1.42', '192.168.1.0'],
    ['10.0.0.255', '10.0.0.0'],
    ['8.8.8.8', '8.8.8.0'],
    ['127.0.0.1', '127.0.0.0'],
]);

it('zeroes the last five groups of an IPv6 address', function (string $input, string $expected) {
    expect($this->anonymizer->anonymize($input))->toBe($expected);
})->with([
    // Compressed input must still expand to eight groups before truncation.
    ['2001:db8::1', '2001:db8::'],
    ['2001:0db8:85a3:0000:0000:8a2e:0370:7334', '2001:db8:85a3::'],
    ['fe80::1ff:fe23:4567:890a', 'fe80::'],
    ['::1', '::'],
]);

it('always produces a valid IPv6 address', function (string $input) {
    $result = $this->anonymizer->anonymize($input);

    expect(filter_var($result, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))->not->toBeFalse();
})->with([
    '2001:db8::1',
    '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
    'fe80::1ff:fe23:4567:890a',
    '::1',
    '2400:cb00:2048:0001:0000:0000:6ca3:643d',
]);

it('preserves the network prefix while discarding the host portion', function () {
    $a = $this->anonymizer->anonymize('2001:db8:85a3:1111:2222:3333:4444:5555');
    $b = $this->anonymizer->anonymize('2001:db8:85a3:9999:8888:7777:6666:5555');

    expect($a)->toBe($b)->toBe('2001:db8:85a3::');
});

it('falls back to a placeholder for unusable input', function (?string $input) {
    expect($this->anonymizer->anonymize($input))->toBe('0.0.0.0');
})->with([null, '', 'not-an-ip', 'javascript:alert(1)']);
