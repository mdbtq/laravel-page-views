<?php

namespace Mdbtq\PageViews\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Mdbtq\PageViews\Models\PageView;

class PageViewStatsCommand extends Command
{
    protected $signature = 'pageviews:stats
        {--days=30 : Number of days to look back}
        {--top=10 : Number of top entries to show per breakdown}
        {--json : Output the statistics as JSON}';

    protected $description = 'Display page view statistics';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $top = max(1, (int) $this->option('top'));
        $since = now()->subDays($days);

        $stats = [
            'days' => $days,
            'since' => $since->toDateTimeString(),
            'total_views' => $this->query($since)->count(),
            'unique_visitors' => $this->uniqueVisitors($since),
            'daily' => $this->daily($since, $days),
            'top_pages' => $this->breakdown($since, 'path', $top),
            'top_routes' => $this->breakdown($since, 'route', $top),
            'top_referrers' => $this->breakdown($since, 'referrer_host', $top),
            'top_countries' => $this->breakdown($since, 'country', $top),
            'top_campaigns' => $this->breakdown($since, 'utm_campaign', $top),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->render($stats, $top);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function render(array $stats, int $top): void
    {
        $this->info("=== Page View Stats (last {$stats['days']} days) ===");
        $this->newLine();
        $this->info("Total views: {$stats['total_views']}");
        $this->info("Unique visitors: {$stats['unique_visitors']}");

        $this->renderTable('Daily Views', ['Date', 'Views'], $stats['daily']);
        $this->renderTable("Top {$top} Pages", ['Path', 'Views'], $stats['top_pages']);
        $this->renderTable("Top {$top} Routes", ['Route', 'Views'], $stats['top_routes']);
        $this->renderTable("Top {$top} Referrers", ['Referrer', 'Views'], $stats['top_referrers']);
        $this->renderTable("Top {$top} Countries", ['Country', 'Views'], $stats['top_countries']);
        $this->renderTable("Top {$top} Campaigns", ['Campaign', 'Views'], $stats['top_campaigns']);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function renderTable(string $title, array $headers, array $rows): void
    {
        $this->newLine();
        $this->info("--- {$title} ---");

        if ($rows === []) {
            $this->line('No data.');

            return;
        }

        $this->table($headers, array_map(array_values(...), $rows));
    }

    /**
     * Aggregate views grouped by a single column, most viewed first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(\DateTimeInterface $since, string $column, int $limit): array
    {
        return $this->baseQuery($since)
            ->select([$column, DB::raw('COUNT(*) as views')])
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [$column => $row->{$column}, 'views' => (int) $row->views])
            ->all();
    }

    /**
     * Views per calendar day, newest first. Bounded by the reporting window
     * rather than by --top, which only governs the ranked breakdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    private function daily(\DateTimeInterface $since, int $days): array
    {
        $date = $this->dateExpression();

        return $this->baseQuery($since)
            ->select([DB::raw("{$date} as date"), DB::raw('COUNT(*) as views')])
            ->groupBy(DB::raw($date))
            ->orderByDesc(DB::raw($date))
            ->limit($days)
            ->get()
            ->map(fn (object $row): array => ['date' => (string) $row->date, 'views' => (int) $row->views])
            ->all();
    }

    /**
     * Count distinct visitors, falling back to the anonymized IP for rows
     * recorded before visitor hashing was enabled.
     */
    private function uniqueVisitors(\DateTimeInterface $since): int
    {
        return (int) $this->baseQuery($since)
            ->distinct()
            ->count(DB::raw('COALESCE(visitor_hash, ip_anon)'));
    }

    /**
     * @return Builder<PageView>
     */
    private function query(\DateTimeInterface $since): Builder
    {
        return PageView::query()->since($since);
    }

    /**
     * Truncate the timestamp to a calendar date. Postgres has no DATE()
     * function, so it gets a cast instead.
     */
    private function dateExpression(): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? 'CAST(viewed_at AS date)'
            : 'DATE(viewed_at)';
    }

    /**
     * Aggregate queries return computed columns rather than model attributes,
     * so they run through the query builder.
     */
    private function baseQuery(\DateTimeInterface $since): QueryBuilder
    {
        return DB::table((new PageView)->getTable())->where('viewed_at', '>=', $since);
    }
}
