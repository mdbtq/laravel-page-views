# Laravel Page Views

Privacy-first pageview tracking for Laravel. Records page views with anonymized IPs, cookieless visitor counting, bot filtering and optional country resolution — all without external services.

- No cookies and nothing read from the device, so no consent banner
- No third-party requests; nothing leaves your server
- IP addresses are truncated and the raw User-Agent is never stored
- Visitors are counted through a daily-rotating hash whose inputs are not retained
- Works behind full-page caching by importing access logs
- Writes happen after the response is sent, and never break the request

## Requirements

- PHP 8.2+ (PHP 8.3+ for Laravel 13)
- Laravel 12 or 13

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
| `browser`, `platform` | Coarse labels (`Chrome`, `macOS`); the raw User-Agent is never stored |
| `ip_anon` | IPv4 truncated to /24, IPv6 to /48 |
| `visitor_hash` | Daily-rotating SHA-256 of IP + User-Agent + salt; inputs are not retained |
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
| `visitor_hash.rotate_days` | `30` | Rotate the salt every N days; `0` disables (`PAGE_VIEWS_SALT_ROTATE_DAYS`) |
| `track_utm` | `true` | Capture UTM campaign parameters |
| `driver` | `sync` | `sync` or `queue` (`PAGE_VIEWS_DRIVER`) |
| `purge_days` | `90` | Default retention for the purge command |
| `schedule.purge` | Disabled | Run the purge daily |
| `schedule.rollup` | Disabled | Run the rollup daily |

### Visitor counting

Unique visitors are counted through a hash of the IP address, the User-Agent and
a salt that includes the current date. Because the date is part of the input, the
same visitor produces a different hash tomorrow, and yesterday's records cannot
be correlated with today's.

That rotation only holds if the hash inputs are not recoverable, which is why the
raw User-Agent is never stored and the IP is truncated before it is written. The
stored row does not contain enough to recompute its own hash.

Set a dedicated salt so the hashes do not depend on a secret used for everything
else:

```env
PAGE_VIEWS_SALT=some-long-random-string
PAGE_VIEWS_SALT_ROTATE_DAYS=30
```

The salt itself rotates every 30 days by default, bounding how long a single
secret governs the whole table. Rotation deliberately makes hashes from earlier
periods unreproducible, so returning-visitor comparisons do not span a boundary.
Set `PAGE_VIEWS_SALT_ROTATE_DAYS=0` to keep one static salt.

When `visitor_hash.enabled` is `false`, unique-visitor counts fall back to
distinct anonymized IPs.

### What this does and does not claim

The package stores no cookies and reads nothing from the visitor's device, so it
stays outside the consent requirement in article 5(3) of the ePrivacy directive
(in the Netherlands, article 11.7a Tw). That is the basis for running it without
a cookie banner.

It does not follow that the GDPR stops applying. You still need a lawful basis
for the processing (typically legitimate interest), a retention period that is
actually enforced, and a mention in your privacy statement. The defaults here are
built to support that — anonymized IPs, no raw User-Agent, rotating salts, and a
scheduled purge — but the assessment is yours to make, and this is not legal
advice.

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

# Import from an access log
php artisan pageviews:import /var/log/nginx/access.log
php artisan pageviews:import access.log --since=2024-10-01 --host=example.com
php artisan pageviews:import access.log --dry-run
cat access.log | php artisan pageviews:import -
```

`--days` sets the reporting window and `--top` limits the ranked breakdowns; the daily series always covers the full window.

### Importing from access logs

The middleware only sees requests that reach PHP. Full-page caching at a CDN or
in nginx serves a hit without ever touching Laravel, so those visits are missing
from the table. `pageviews:import` closes that gap by reading the access log,
which records the request regardless of who answered it.

Log entries run through the same recorder as live traffic, so bot filtering,
excluded paths, IP anonymization and visitor hashing behave identically. The
visitor hash is derived with the salt of the day the request was served, which
keeps the daily rotation intact for imported rows.

Two formats are recognised: the NCSA combined format that nginx and Apache emit
by default, and JSON, which covers most CDN log pipelines (field names from
nginx and Cloudflare are both understood). Lines that cannot be parsed are
counted and skipped rather than aborting the run.

| Option | Purpose |
| --- | --- |
| `--since` | Skip entries before this date/time |
| `--timezone` | Timezone of log timestamps without an explicit offset |
| `--host` | Hostname used to resolve paths from absolute URLs |
| `--chunk` | Rows per insert statement |
| `--dry-run` | Report what would be imported without writing |

The command has no memory of previous runs, so overlapping imports insert
duplicates. Use `--since` to bound each run, or import whole rotated log files
exactly once.

### Retention and history

`pageviews:purge` deletes raw rows in chunks so a large backlog does not hold one long transaction open. `pageviews:rollup` aggregates raw views into `page_view_daily` (per day, path and country), which keeps long-term history even when raw rows are purged aggressively. Re-running the rollup for a day replaces that day's totals, so it is safe to run repeatedly.

Both are scheduled by default, so the retention period in `purge_days` is
enforced rather than advisory. The rollup runs first, preserving long-term totals
in `page_view_daily` before the purge removes the raw rows behind them.

This requires the Laravel scheduler to be running. Without it nothing is purged
and the table grows indefinitely.

Disable either one if you want to run them yourself:

```env
PAGE_VIEWS_SCHEDULE_PURGE=false
PAGE_VIEWS_SCHEDULE_ROLLUP=false
```

## Testing

```bash
composer test
```

The suite runs on SQLite by default. Set `DB_DRIVER` to `mysql` or `pgsql` to run it against a real database; CI runs all three.

## License

MIT
