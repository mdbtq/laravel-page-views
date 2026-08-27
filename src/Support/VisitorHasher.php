<?php

namespace Mdbtq\PageViews\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;

/**
 * Derives a cookieless visitor identifier.
 *
 * The date is part of the hash input, so the same visitor produces a
 * different value tomorrow and yesterday's records cannot be correlated
 * with today's. The configured salt is additionally rotated on a fixed
 * period, which bounds how long a single secret governs the whole table.
 */
class VisitorHasher
{
    public function __construct(private readonly Config $config) {}

    public function hash(?string $ip, string $userAgent, string $date): ?string
    {
        if (! $this->config->get('page-views.visitor_hash.enabled', true)) {
            return null;
        }

        return hash('sha256', implode('|', [
            $date,
            $this->saltFor($date),
            $ip ?? '',
            $userAgent,
        ]));
    }

    /**
     * The salt in effect for a given date.
     *
     * Rotating derives a period-scoped salt from the configured secret, so
     * hashes cannot be linked across periods even by someone holding it.
     * Deriving rather than storing keeps rotation stateless, which matters
     * because imports have to reproduce the salt of a past day.
     */
    private function saltFor(string $date): string
    {
        $salt = $this->config->get('page-views.visitor_hash.salt')
            ?: $this->config->get('app.key', '');

        $days = (int) $this->config->get('page-views.visitor_hash.rotate_days', 0);

        if ($days < 1) {
            return (string) $salt;
        }

        return hash_hmac('sha256', 'period:'.$this->periodFor($date, $days), (string) $salt);
    }

    /**
     * Index of the rotation period a date falls in, counted from the Unix
     * epoch so the boundaries are stable across deploys and machines.
     */
    private function periodFor(string $date, int $days): int
    {
        return intdiv(Carbon::parse($date)->startOfDay()->getTimestamp(), $days * 86400);
    }
}
