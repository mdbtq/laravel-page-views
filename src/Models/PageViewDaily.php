<?php

namespace Mdbtq\PageViews\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pre-aggregated daily totals, so history survives the purge of raw rows.
 *
 * @property Carbon $date
 * @property string $path
 * @property string|null $country
 * @property int $views
 * @property int $visitors
 */
class PageViewDaily extends Model
{
    protected $table = 'page_view_daily';

    public $timestamps = false;

    protected $fillable = [
        'date',
        'path',
        'country',
        'views',
        'visitors',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'views' => 'integer',
            'visitors' => 'integer',
        ];
    }
}
