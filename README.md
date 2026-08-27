# Laravel Page Views

Privacy-first pageview tracking for Laravel. Records page views with anonymized IPs, cookieless visitor counting, bot filtering and optional country resolution — all without external services.

- No cookies, no consent banner, no third-party requests
- IP addresses are truncated before they are stored
- Visitors are counted through a daily-rotating hash that cannot be traced back to a person
- Writes happen after the response is sent, and never break the request

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require mdbtq/laravel-page-views
php artisan migrate
```

The migration ships with the package and runs from there. You only need to publish it if you want to edit the schema:

```bash
php artisan vendor:publish --tag=page-views-migrations
```

Once a copy exists in `database/migrations`, the package stops loading its own so the migration is never registered twice.

Optionally publish the config:

```bash
php artisan vendor:publish --tag=page-views-config
```

## Setup

Register the middleware in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Mdbtq\PageViews\Middleware\TrackPageView::class);
})
```

Tracking runs in the middleware's `terminate()` method, so it happens after the response has been sent to the browser.

## What gets recorded

| Column | Notes |
|--------|-------|
| `path` | Request path without query string |
| `route` | Named route, so `/blog/{slug}` aggregates as one entry |
| `referrer` | Full referrer URL |
| `referrer_host` | Bare host (`google.com`), for fast grouping |
| `utm_source`, `utm_medium`, `utm_campaign` | Campaign attribution; the rest of the query string is discarded |
| `user_agent` | Raw User-Agent string |
| `ip_anon` | IPv4 truncated to /24, IPv6 to /48 |
| `visitor_hash` | Daily-rotating SHA-256 of IP + User-Agent + salt |
| `country` | Two-letter code, when a geo resolver is installed |
| `viewed_at` | Timestamp |

A page view is skipped when the request is not a `GET`, the response status is not trackable, the path is excluded, the User-Agent looks like a bot, or the visitor sends a Do Not Track signal.

## Configuration

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Master switch (`PAGE_VIEWS_ENABLED`) |
| `excluded_paths` | Static asset pattern | Regex for paths to skip |
| `bot_signatures` | Common bot UA strings | User-Agent substrings to filter |
| `empty_user_agent_is_bot` | `true` | Treat a missing User-Agent as automated traffic |
| `respect_do_not_track` | `true` | Honour `DNT: 1` and `Sec-GPC: 1` |
| `trackable_status_codes` | `[200, 304]` | Status codes that count as a view |
| `anonymize_ip` | `true` | Truncate IPv4 to /24 and IPv6 to /48 |
| `visitor_hash.enabled` | `true` | Enable cookieless visitor counting |
| `visitor_hash.salt` | App key | Hash salt (`PAGE_VIEWS_SALT`) |
| `track_utm` | `true` | Capture UTM campaign parameters |
| `driver` | `sync` | `sync` or `queue` (`PAGE_VIEWS_DRIVER`) |
| `purge_days` | `90` | Default retention for the purge command |
| `schedule.purge` | Disabled | Run the purge daily |
| `schedule.rollup` | Disabled | Run the rollup daily |

### Visitor counting

Unique visitors are counted through a hash of the IP address, the User-Agent and a salt that includes the current date. Because the date is part of the input, the same visitor produces a different hash tomorrow, and yesterday's records cannot be correlated with today's. Nothing that identifies a person is stored.

Set a dedicated salt to keep the value independent of `APP_KEY`:

```env
PAGE_VIEWS_SALT=some-long-random-string
```

When `visitor_hash.enabled` is `false`, unique-visitor counts fall back to distinct anonymized IPs.

### Queued writes

On busy sites, write the record from a queue worker instead of the web process:

```env
PAGE_VIEWS_DRIVER=queue
PAGE_VIEWS_QUEUE_CONNECTION=redis
PAGE_VIEWS_QUEUE=analytics
```

### Excluding requests yourself

Register a condition from a service provider to veto page views the config cannot express:

```php
use Mdbtq\PageViews\PageViewRecorder;

$this->app->make(PageViewRecorder::class)->trackWhen(
    fn ($request) => ! $request->user()?->isAdmin()
);
```

### Reacting to page views

Every recorded view dispatches an event:

```php
use Mdbtq\PageViews\Events\PageViewRecorded;

Event::listen(PageViewRecorded::class, function (PageViewRecorded $event) {
    // $event->pageView
});
```

## Country Resolution

Install [torann/geoip](https://github.com/Torann/laravel-geoip) for automatic country detection:

```bash
composer require torann/geoip
```

The package auto-detects its presence — no additional configuration needed. Addresses the resolver cannot place are stored as `null` rather than as a default country.

## Commands

```bash
# View statistics
php artisan pageviews:stats
php artisan pageviews:stats --days=7 --top=20
php artisan pageviews:stats --json

# Aggregate into daily totals
php artisan pageviews:rollup
php artisan pageviews:rollup --days=30

# Purge old records
php artisan pageviews:purge
php artisan pageviews:purge --days=30
php artisan pageviews:purge --dry-run
```

`--days` sets the reporting window and `--top` limits the ranked breakdowns; the daily series always covers the full window.

### Retention and history

`pageviews:purge` deletes raw rows in chunks so a large backlog does not hold one long transaction open. `pageviews:rollup` aggregates raw views into `page_view_daily` (per day, path and country), which keeps long-term history even when raw rows are purged aggressively. Re-running the rollup for a day replaces that day's totals, so it is safe to run repeatedly.

Enable both in the config to have the package schedule them for you:

```php
'schedule' => [
    'purge' => ['enabled' => true, 'at' => '03:00'],
    'rollup' => ['enabled' => true, 'at' => '02:00'],
],
```

## Testing

```bash
composer test
```

The suite runs on SQLite by default. Set `DB_DRIVER` to `mysql` or `pgsql` to run it against a real database; CI runs all three.

## License

MIT
