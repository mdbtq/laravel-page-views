<?php

namespace Mdbtq\PageViews\Support;

class ReferrerParser
{
    /**
     * Reduce a referrer URL to its bare host, so that stats group on a short
     * indexable column instead of on full URLs with tracking parameters.
     */
    public function host(?string $referrer): ?string
    {
        if ($referrer === null || $referrer === '') {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
