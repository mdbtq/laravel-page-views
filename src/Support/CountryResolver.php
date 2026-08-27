<?php

namespace Mdbtq\PageViews\Support;

class CountryResolver
{
    /**
     * Resolve a two-letter country code via torann/geoip when installed.
     *
     * The default location the package returns for unknown addresses is
     * treated as "no result" rather than as a real country.
     */
    public function resolve(?string $ip): ?string
    {
        if ($ip === null || ! function_exists('geoip')) {
            return null;
        }

        try {
            $location = geoip($ip);

            if ($location === null || ($location->default ?? true)) {
                return null;
            }

            $iso = $location->iso_code ?: null;

            return is_string($iso) ? strtoupper(substr($iso, 0, 2)) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
