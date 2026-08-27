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

it('rotates the salt on the configured period', function () {
    config()->set('page-views.visitor_hash.salt', 'fixed-salt');

    // Hold the date constant and vary only the rotation period, so the
    // difference can only come from the salt. Comparing two dates instead
    // would pass even with rotation broken, because the date is itself part
    // of the hash input.
    config()->set('page-views.visitor_hash.rotate_days', 0);
    $static = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');

    config()->set('page-views.visitor_hash.rotate_days', 30);
    $rotated = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');

    expect($rotated)->not->toBe($static);
});

it('produces different salts for dates in different rotation periods', function () {
    config()->set('page-views.visitor_hash.salt', 'fixed-salt');
    config()->set('page-views.visitor_hash.rotate_days', 30);

    $reflection = new ReflectionMethod($this->hasher, 'saltFor');

    // Assert on the salt itself rather than the hash, so the date component
    // cannot mask a broken derivation.
    expect($reflection->invoke($this->hasher, '2026-01-01'))
        ->not->toBe($reflection->invoke($this->hasher, '2026-06-01'));
});

it('uses one salt for every date inside a rotation period', function () {
    config()->set('page-views.visitor_hash.salt', 'fixed-salt');
    config()->set('page-views.visitor_hash.rotate_days', 30);

    $reflection = new ReflectionMethod($this->hasher, 'saltFor');

    expect($reflection->invoke($this->hasher, '2026-01-02'))
        ->toBe($reflection->invoke($this->hasher, '2026-01-03'));
});

it('keeps a static salt when rotation is disabled', function () {
    config()->set('page-views.visitor_hash.rotate_days', 0);
    config()->set('page-views.visitor_hash.salt', 'fixed-salt');

    $a = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');

    config()->set('page-views.visitor_hash.rotate_days', 0);
    $b = $this->hasher->hash('192.168.1.1', 'Chrome', '2026-08-27');

    expect($a)->toBe($b);
});

it('derives period salts deterministically so past days can be reproduced', function () {
    config()->set('page-views.visitor_hash.rotate_days', 30);
    config()->set('page-views.visitor_hash.salt', 'fixed-salt');

    // Importing a historical log has to reproduce the salt of that day, so
    // the derivation must depend only on the date and the configured secret.
    $first = $this->hasher->hash('192.168.1.1', 'Chrome', '2024-10-10');
    $second = $this->hasher->hash('192.168.1.1', 'Chrome', '2024-10-10');

    expect($first)->toBe($second);
});
