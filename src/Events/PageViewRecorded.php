<?php

namespace Mdbtq\PageViews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Mdbtq\PageViews\Models\PageView;

class PageViewRecorded
{
    use Dispatchable;

    public function __construct(public readonly PageView $pageView) {}
}
