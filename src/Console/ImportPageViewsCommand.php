<?php

namespace Mdbtq\PageViews\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Mdbtq\PageViews\Models\PageView;
use Mdbtq\PageViews\PageViewRecorder;
use Mdbtq\PageViews\Support\LogLineParser;
use Symfony\Component\HttpFoundation\Response;

class ImportPageViewsCommand extends Command
{
    protected $signature = 'pageviews:import
        {file? : Path to the access log, or "-" to read from STDIN}
        {--since= : Only import entries at or after this date/time}
        {--timezone= : Timezone of the log timestamps (defaults to the app timezone)}
        {--host= : Hostname to attribute the entries to, for path resolution}
        {--chunk=500 : Number of records to insert per statement}
        {--dry-run : Report what would be imported without writing it}';

    protected $description = 'Import page views from a webserver or CDN access log';

    public function handle(PageViewRecorder $recorder, LogLineParser $parser): int
    {
        $file = (string) ($this->argument('file') ?? '-');
        $handle = $this->openLog($file);

        if ($handle === null) {
            $this->error("Unable to read log file [{$file}].");

            return self::FAILURE;
        }

        $since = $this->resolveSince();
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $stats = ['read' => 0, 'skipped' => 0, 'unparsable' => 0, 'imported' => 0];
        $batch = [];

        while (($line = fgets($handle)) !== false) {
            $stats['read']++;

            $entry = $parser->parse($line);

            if ($entry === null) {
                $stats['unparsable']++;

                continue;
            }

            $viewedAt = $this->resolveTimestamp($entry['time']);

            if ($viewedAt === null || ($since !== null && $viewedAt->lt($since))) {
                $stats['skipped']++;

                continue;
            }

            $attributes = $this->attributesFor($recorder, $entry, $viewedAt);

            if ($attributes === null) {
                $stats['skipped']++;

                continue;
            }

            $batch[] = $attributes;

            if (count($batch) >= $chunk) {
                $stats['imported'] += $this->flush($batch, $dryRun);
                $batch = [];
            }
        }

        if ($file !== '-') {
            fclose($handle);
        }

        $stats['imported'] += $this->flush($batch, $dryRun);

        $this->report($stats, $dryRun);

        return self::SUCCESS;
    }

    /**
     * Build the stored attributes by running the log entry through the same
     * recorder the middleware uses, so filtering, anonymization and hashing
     * behave identically for imported and live traffic.
     *
     * @param  array{ip: string, time: string, method: string, target: string, status: int, referer: string|null, agent: string}  $entry
     * @return array<string, mixed>|null
     */
    private function attributesFor(PageViewRecorder $recorder, array $entry, Carbon $viewedAt): ?array
    {
        $request = $this->requestFor($entry);
        $response = new Response('', $entry['status']);

        if (! $recorder->shouldTrack($request, $response)) {
            return null;
        }

        // The recorder derives the visitor hash from "today"; imported rows
        // must use the salt of the day they were served instead.
        return Carbon::withTestNow($viewedAt, fn (): array => $recorder->attributesFor($request));
    }

    /**
     * Rebuild a Request from the log entry so the recorder sees the same
     * shape of input it gets from a live request.
     *
     * @param  array{ip: string, time: string, method: string, target: string, status: int, referer: string|null, agent: string}  $entry
     */
    private function requestFor(array $entry): Request
    {
        $host = (string) ($this->option('host') ?: 'localhost');
        $target = $entry['target'];

        // CDN logs sometimes record an absolute URL rather than a path.
        $uri = str_starts_with($target, 'http://') || str_starts_with($target, 'https://')
            ? $target
            : 'http://'.$host.'/'.ltrim($target, '/');

        $request = Request::create($uri, $entry['method'], server: array_filter([
            'REMOTE_ADDR' => $entry['ip'],
            'HTTP_USER_AGENT' => $entry['agent'],
            'HTTP_REFERER' => $entry['referer'],
        ], is_string(...)));

        return $request;
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function flush(array $batch, bool $dryRun): int
    {
        if ($batch === []) {
            return 0;
        }

        if (! $dryRun) {
            PageView::query()->insert($batch);
        }

        return count($batch);
    }

    /**
     * @return resource|null
     */
    private function openLog(string $file)
    {
        if ($file === '-') {
            return STDIN;
        }

        if (! is_file($file) || ! is_readable($file)) {
            return null;
        }

        $handle = fopen($file, 'rb');

        return $handle === false ? null : $handle;
    }

    /**
     * Parse a log timestamp. Accepts the bracketed NCSA format as well as
     * anything strtotime() understands, which covers ISO-8601 from CDNs.
     */
    private function resolveTimestamp(string $time): ?Carbon
    {
        if ($time === '') {
            return null;
        }

        $timezone = (string) ($this->option('timezone') ?: config('app.timezone', 'UTC'));

        $parsed = $this->parseNcsa($time) ?? $this->parseLoosely($time);

        if ($parsed === null) {
            return null;
        }

        // A log line carrying its own offset is already absolute; one without
        // is wall-clock time in the log's timezone.
        return $this->hasExplicitOffset($time)
            ? $parsed->setTimezone(config('app.timezone', 'UTC'))
            : Carbon::parse($parsed->format('Y-m-d H:i:s'), $timezone)
                ->setTimezone(config('app.timezone', 'UTC'));
    }

    /**
     * NCSA timestamps ("10/Oct/2000:13:55:36 -0700") use a d/M/Y date that
     * strtotime() does not recognise, so they get an explicit format.
     */
    private function parseNcsa(string $time): ?Carbon
    {
        try {
            return Carbon::rawCreateFromFormat('d/M/Y:H:i:s P', trim($time)) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseLoosely(string $time): ?Carbon
    {
        try {
            return Carbon::parse($time);
        } catch (\Throwable) {
            return null;
        }
    }

    private function hasExplicitOffset(string $time): bool
    {
        return preg_match('/(Z|[+-]\d{2}:?\d{2})\s*$/', trim($time)) === 1;
    }

    private function resolveSince(): ?Carbon
    {
        $since = $this->option('since');

        if ($since === null || $since === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $since);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function report(array $stats, bool $dryRun): void
    {
        $verb = $dryRun ? 'Would import' : 'Imported';

        $this->info("{$verb} {$stats['imported']} page views from {$stats['read']} log lines.");

        if ($stats['skipped'] > 0) {
            $this->line("Skipped {$stats['skipped']} entries (filtered, or before --since).");
        }

        if ($stats['unparsable'] > 0) {
            $this->line("Ignored {$stats['unparsable']} unparsable lines.");
        }
    }
}
