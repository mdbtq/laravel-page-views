<?php

namespace Mdbtq\PageViews\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $path
 * @property string|null $route
 * @property string|null $referrer
 * @property string|null $referrer_host
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $browser
 * @property string|null $platform
 * @property string $ip_anon
 * @property string|null $visitor_hash
 * @property string|null $country
 * @property Carbon $viewed_at
 */
class PageView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'path',
        'route',
        'referrer',
        'referrer_host',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'browser',
        'platform',
        'ip_anon',
        'visitor_hash',
        'country',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSince(Builder $query, \DateTimeInterface $since): void
    {
        $query->where('viewed_at', '>=', $since);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->whereBetween('viewed_at', [$from, $to]);
    }
}
