<?php

use Mdbtq\PageViews\Support\VisitorHasher;

beforeEach(function () {
    $this->hasher = app(VisitorHasher::class);
});

it('produces a stable hash for the same visitor on the same day', function () {
    $a = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');
    $b = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');

    expect($a)->toBe($b)->toHaveLength(64);
});

it('rotates the hash daily so visits cannot be linked across days', function () {
    $today = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');
    $tomorrow = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-28');

    expect($today)->not->toBe($tomorrow);
});

it('separates visitors by ip and user agent', function () {
    $base = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');

    expect($this->hasher->hash('192.168.1.2', 'Chrome', '2026-08-27'))->not->toBe($base)
        ->and($this->hasher->hash('192.168.1.1', 'Firefox', '2026-08-27'))->not->toBe($base);
});

it('does not store the raw ip address in the hash', function () {
    $hash = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');

    expect($hash)->not->toContain('192.168.1.1')
        ->and($hash)->toMatch('/^[a-f0-9]{64}$/');
});

it('returns null when disabled', function () {
    config()->set('page-views.visitor_hash.enabled', false);

    expect($this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27'))->toBeNull();
});

it('uses a configured salt over the app key', function () {
    config()->set('page-views.visitor_hash.salt', 'salt-one');
    $a = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');

    config()->set('page-views.visitor_hash.salt', 'salt-two');
    $b = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');

    expect($a)->not->toBe($b);
});
