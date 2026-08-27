<?php

namespace Mdbtq\PageViews\Console;

use Illuminate\Console\Command;
use Mdbtq\PageViews\Models\PageView;

class PurgePageViewsCommand extends Command
{
    protected $signature = 'pageviews:purge
        {--days= : Delete records older than this many days}
        {--chunk=1000 : Number of records to delete per statement}
        {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Purge old page view records';

    public function handle(): int
    {
        $option = $this->option('days');
        $days = $option === null ? (int) config('page-views.purge_days', 90) : (int) $option;

        if ($days < 1) {
            $this->error('The number of days must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $query = PageView::query()->where('viewed_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info("Would purge {$count} page view records older than {$days} days ({$cutoff->toDateTimeString()}).");

            return self::SUCCESS;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $deleted = 0;

        // Delete in chunks so a large backlog does not hold a single long
        // transaction open on the table.
        do {
            $affected = $query->clone()->limit($chunk)->delete();
            $deleted += $affected;
        } while ($affected > 0);

        $this->info("Purged {$deleted} page view records older than {$days} days.");

        return self::SUCCESS;
    }
}
