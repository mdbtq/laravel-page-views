<?php

namespace Mdbtq\PageViews\Support;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Derives a cookieless visitor identifier.
 *
 * The salt rotates daily, so the same visitor produces a different hash
 * tomorrow and yesterday's records cannot be correlated with today's.
 */
class VisitorHasher
{
    public function __construct(private readonly Config $config) {}

    public function hash(?string $ip, string $userAgent, string $date): ?string
    {
        if (! $this->config->get('page-views.visitor_hash.enabled', true)) {
            return null;
        }

        $salt = $this->config->get('page-views.visitor_hash.salt')
            ?: $this->config->get('app.key', '');

        return hash('sha256', implode('|', [$date, $salt, $ip ?? '', $userAgent]));
    }
}
