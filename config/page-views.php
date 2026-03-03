<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Excluded Paths
    |--------------------------------------------------------------------------
    |
    | Regex pattern for paths that should not be tracked. Matched against the
    | request path (without leading slash). The default pattern excludes
    | common static asset paths.
    |
    */

    'excluded_paths' => '#^(build/|assets/|fonts/|images/|favicon\.|robots\.txt|sitemap\.xml|up$)#i',

    /*
    |--------------------------------------------------------------------------
    | Bot Signatures
    |--------------------------------------------------------------------------
    |
    | User-Agent substrings used to identify bots. Matching is
    | case-insensitive. Add entries to filter additional crawlers.
    |
    */

    'bot_signatures' => [
        'bot', 'crawl', 'spider', 'slurp', 'wget', 'curl',
        'python', 'java/', 'httpclient', 'fetcher', 'scanner',
        'lighthouse', 'pagespeed', 'headlesschrome', 'phantomjs',
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Anonymization
    |--------------------------------------------------------------------------
    |
    | When enabled, the last octet of IPv4 addresses and the last five
    | groups of IPv6 addresses are zeroed before storage.
    |
    */

    'anonymize_ip' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Purge Days
    |--------------------------------------------------------------------------
    |
    | The default number of days to retain page view records. Used by the
    | pageviews:purge command when no --days option is provided.
    |
    */

    'purge_days' => 90,

];
