<?php

namespace Mdbtq\PageViews\Console;

use Illuminate\Console\Command;
use Mdbtq\PageViews\Models\PageView;

class PurgePageViewsCommand extends Command
{
    protected $signature = 'pageviews:purge
        {--days= : Delete records older than this many days}';

    protected $description = 'Purge old page view records';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('page-views.purge_days', 90));
        $cutoff = now()->subDays($days);
        $deleted = PageView::where('viewed_at', '<', $cutoff)->delete();

        $this->info("Purged {$deleted} page view records older than {$days} days.");

        return self::SUCCESS;
    }
}
