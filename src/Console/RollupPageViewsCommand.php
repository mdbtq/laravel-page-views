<?php

namespace Mdbtq\PageViews\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mdbtq\PageViews\Models\PageView;
use Mdbtq\PageViews\Models\PageViewDaily;

class RollupPageViewsCommand extends Command
{
    protected $signature = 'pageviews:rollup
        {--days=7 : Number of past days to (re)aggregate}';

    protected $description = 'Aggregate raw page views into daily totals';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $rows = 0;

        for ($offset = $days; $offset >= 0; $offset--) {
            $rows += $this->rollupDay(now()->subDays($offset)->startOfDay());
        }

        $this->info("Rolled up {$rows} daily aggregate rows for the last {$days} days.");

        return self::SUCCESS;
    }

    /**
     * Aggregate a single day, replacing any previous totals for it so the
     * command is safe to re-run.
     */
    private function rollupDay(Carbon $day): int
    {
        $date = $day->toDateString();

        $aggregates = DB::table((new PageView)->getTable())
            ->whereBetween('viewed_at', [$day, $day->copy()->endOfDay()])
            ->select([
                'path',
                'country',
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT COALESCE(visitor_hash, ip_anon)) as visitors'),
            ])
            ->groupBy('path', 'country')
            ->get();

        PageViewDaily::query()->where('date', $date)->delete();

        foreach ($aggregates->chunk(500) as $chunk) {
            PageViewDaily::query()->insert(
                $chunk->map(fn (object $row): array => [
                    'date' => $date,
                    'path' => $row->path,
                    'country' => $row->country,
                    'views' => (int) $row->views,
                    'visitors' => (int) $row->visitors,
                ])->all()
            );
        }

        return $aggregates->count();
    }
}
