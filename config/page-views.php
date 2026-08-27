<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. When disabled the middleware records nothing, which is
    | useful for local development and test suites.
    |
    */

    'enabled' => env('PAGE_VIEWS_ENABLED', true),

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
    | Treat Empty User-Agent As Bot
    |--------------------------------------------------------------------------
    |
    | Requests without a User-Agent header are almost always automated. When
    | enabled they are filtered out alongside the signature matches.
    |
    */

    'empty_user_agent_is_bot' => true,

    /*
    |--------------------------------------------------------------------------
    | Respect Do Not Track
    |--------------------------------------------------------------------------
    |
    | When enabled, requests sending "DNT: 1" or "Sec-GPC: 1" are not
    | recorded at all.
    |
    */

    'respect_do_not_track' => true,

    /*
    |--------------------------------------------------------------------------
    | Trackable Status Codes
    |--------------------------------------------------------------------------
    |
    | Response status codes that count as a page view. 304 is included
    | because a cached page is still a visit.
    |
    */

    'trackable_status_codes' => [200, 304],

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
    | Visitor Hash
    |--------------------------------------------------------------------------
    |
    | A cookieless visitor identifier derived from the IP address, the
    | User-Agent and a salt that rotates daily. This yields unique-visitor
    | counts without storing anything that can be traced back to a person.
    | The salt defaults to the application key; the date rotation means
    | yesterday's hashes cannot be linked to today's.
    |
    */

    'visitor_hash' => [
        'enabled' => true,
        'salt' => env('PAGE_VIEWS_SALT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | UTM Parameters
    |--------------------------------------------------------------------------
    |
    | Campaign parameters are captured from the query string. The rest of
    | the query string is always discarded.
    |
    */

    'track_utm' => true,

    /*
    |--------------------------------------------------------------------------
    | Recording Driver
    |--------------------------------------------------------------------------
    |
    | "sync" writes the record during terminate(), after the response has
    | been sent. "queue" dispatches a job instead, which keeps the PHP
    | worker free on busy sites.
    |
    */

    'driver' => env('PAGE_VIEWS_DRIVER', 'sync'),

    'queue' => [
        'connection' => env('PAGE_VIEWS_QUEUE_CONNECTION'),
        'queue' => env('PAGE_VIEWS_QUEUE'),
    ],

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

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    |
    | When enabled the package registers its own maintenance commands on the
    | scheduler, so retention and rollups are not something you have to
    | remember to wire up.
    |
    */

    'schedule' => [
        'purge' => [
            'enabled' => false,
            'at' => '03:00',
        ],
        'rollup' => [
            'enabled' => false,
            'at' => '02:00',
        ],
    ],

];
