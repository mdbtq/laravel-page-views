# Laravel Page Views

Privacy-first pageview tracking for Laravel. Records page views with anonymized IPs, bot filtering, and optional country resolution — all without external services.

## Installation

```bash
composer require mdbtq/laravel-page-views
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=page-views-migrations
php artisan migrate
```

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

## Configuration

The config file (`config/page-views.php`) exposes:

| Key | Default | Description |
|-----|---------|-------------|
| `excluded_paths` | Static asset pattern | Regex for paths to skip |
| `bot_signatures` | Common bot UA strings | User-Agent substrings to filter |
| `anonymize_ip` | `true` | Zero last octet (IPv4) / last 5 groups (IPv6) |
| `purge_days` | `90` | Default retention for purge command |

## Country Resolution

Install [torann/geoip](https://github.com/Torann/laravel-geoip) for automatic country detection:

```bash
composer require torann/geoip
```

The package auto-detects its presence — no additional configuration needed.

## Commands

```bash
# View statistics
php artisan pageviews:stats
php artisan pageviews:stats --days=7 --top=20

# Purge old records
php artisan pageviews:purge
php artisan pageviews:purge --days=30
```

## License

MIT
