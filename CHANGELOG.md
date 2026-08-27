# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Cookieless unique-visitor counting through a daily-rotating `visitor_hash`.
- UTM campaign capture (`utm_source`, `utm_medium`, `utm_campaign`).
- `referrer_host` column so referrers group by host instead of by full URL.
- `route` column so dynamic paths aggregate under their named route.
- `pageviews:rollup` command and `page_view_daily` table, preserving history beyond the raw-row retention window.
- Do Not Track support for the `DNT` and `Sec-GPC` headers.
- Queued recording via `PAGE_VIEWS_DRIVER=queue`.
- `PageViewRecorded` event and a `PageViewRecorder::trackWhen()` hook for per-request opt-out.
- Optional scheduling of the purge and rollup commands.
- `--json` output for `pageviews:stats`, plus route and campaign breakdowns.
- `--dry-run` and chunked deletes for `pageviews:purge`.
- `enabled` master switch and configurable `trackable_status_codes`.
- Test suite (Pest + Testbench) running on SQLite, MySQL and PostgreSQL, with Pint and PHPStan in CI.

### Changed

- Support Laravel 12 and 13. Laravel 11 is dropped: every 11.x release, up to
  and including the final v11.56.1, is flagged by security advisories with no
  patched release available, so Composer refuses to install it.

### Fixed

- IPv6 anonymization produced invalid addresses. `inet_ntop()` returns the compressed form, so splitting on `:` did not yield eight groups and output such as `2001:db8::0:0:0:0:0` was stored. Addresses are now expanded from their binary form and truncated to a /48.
- Publishing the migration while the package also loaded it caused a duplicate `CREATE TABLE`. The package now stops loading its own migration once a published copy exists.
- The shipped migration had no timestamp prefix, so publishing it placed it before every other migration.
- `304 Not Modified` responses were not counted, undercounting views of cached pages.
- `--top` also truncated the daily series in `pageviews:stats`, so `--days=30 --top=10` silently showed only 10 of 30 days.
- Requests without a User-Agent were treated as human traffic.
- `pageviews:purge --days=0` silently fell back to the configured retention instead of being rejected.
- Daily grouping used `DATE()`, which is not valid on PostgreSQL.

## [0.1.0]

- Initial release.
