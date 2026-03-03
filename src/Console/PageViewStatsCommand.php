<?php

namespace Mdbtq\PageViews\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Mdbtq\PageViews\Models\PageView;

class PageViewStatsCommand extends Command
{
    protected $signature = 'pageviews:stats
        {--days=30 : Number of days to look back}
        {--top=10 : Number of top entries to show}';

    protected $description = 'Display page view statistics';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $top = (int) $this->option('top');
        $since = now()->subDays($days);

        $totalViews = PageView::where('viewed_at', '>=', $since)->count();
        $uniqueIps = PageView::where('viewed_at', '>=', $since)
            ->distinct('ip_anon')->count('ip_anon');

        $this->info("=== Page View Stats (last {$days} days) ===");
        $this->newLine();
        $this->info("Total views: {$totalViews}");
        $this->info("Unique visitors (by anonymized IP): {$uniqueIps}");

        $this->newLine();
        $this->info('--- Daily Views ---');

        $daily = PageView::where('viewed_at', '>=', $since)
            ->select(DB::raw('DATE(viewed_at) as date'), DB::raw('COUNT(*) as views'))
            ->groupBy('date')
            ->orderByDesc('date')
            ->limit($top)
            ->get();

        $this->table(['Date', 'Views'], $daily->map(fn ($r) => [$r->date, $r->views]));

        $this->newLine();
        $this->info("--- Top {$top} Pages ---");

        $pages = PageView::where('viewed_at', '>=', $since)
            ->select('path', DB::raw('COUNT(*) as views'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit($top)
            ->get();

        $this->table(['Path', 'Views'], $pages->map(fn ($r) => [$r->path, $r->views]));

        $this->newLine();
        $this->info("--- Top {$top} Referrers ---");

        $referrers = PageView::where('viewed_at', '>=', $since)
            ->whereNotNull('referrer')
            ->select('referrer', DB::raw('COUNT(*) as views'))
            ->groupBy('referrer')
            ->orderByDesc('views')
            ->limit($top)
            ->get();

        $this->table(['Referrer', 'Views'], $referrers->map(fn ($r) => [$r->referrer, $r->views]));

        $this->newLine();
        $this->info("--- Top {$top} Countries ---");

        $countries = PageView::where('viewed_at', '>=', $since)
            ->whereNotNull('country')
            ->select('country', DB::raw('COUNT(*) as views'))
            ->groupBy('country')
            ->orderByDesc('views')
            ->limit($top)
            ->get();

        $this->table(['Country', 'Views'], $countries->map(fn ($r) => [$r->country, $r->views]));

        return self::SUCCESS;
    }
}
