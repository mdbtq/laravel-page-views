<?php

namespace Mdbtq\PageViews;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Mdbtq\PageViews\Events\PageViewRecorded;
use Mdbtq\PageViews\Jobs\RecordPageView;
use Mdbtq\PageViews\Models\PageView;
use Mdbtq\PageViews\Support\BotDetector;
use Mdbtq\PageViews\Support\CountryResolver;
use Mdbtq\PageViews\Support\IpAnonymizer;
use Mdbtq\PageViews\Support\ReferrerParser;
use Mdbtq\PageViews\Support\UserAgentSummarizer;
use Mdbtq\PageViews\Support\VisitorHasher;
use Symfony\Component\HttpFoundation\Response;

class PageViewRecorder
{
    /**
     * User-supplied predicates that can veto a page view.
     *
     * @var array<int, callable(Request, Response): bool>
     */
    private array $conditions = [];

    public function __construct(
        private readonly Config $config,
        private readonly Dispatcher $events,
        private readonly IpAnonymizer $ipAnonymizer,
        private readonly BotDetector $botDetector,
        private readonly VisitorHasher $visitorHasher,
        private readonly ReferrerParser $referrerParser,
        private readonly UserAgentSummarizer $userAgents,
        private readonly CountryResolver $countryResolver,
    ) {}

    /**
     * Register an additional condition that must pass for a view to be
     * recorded. Lets applications opt out per request without forking.
     *
     * @param  callable(Request, Response): bool  $condition
     */
    public function trackWhen(callable $condition): void
    {
        $this->conditions[] = $condition;
    }

    public function record(Request $request, Response $response): void
    {
        try {
            if (! $this->shouldTrack($request, $response)) {
                return;
            }

            $attributes = $this->attributesFor($request);

            if ($this->config->get('page-views.driver', 'sync') === 'queue') {
                $this->dispatchJob($attributes);

                return;
            }

            $this->store($attributes);
        } catch (\Throwable) {
            // Silently fail — tracking should never break the site
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function store(array $attributes): void
    {
        $pageView = PageView::create($attributes);

        $this->events->dispatch(new PageViewRecorded($pageView));
    }

    public function shouldTrack(Request $request, Response $response): bool
    {
        if (! $this->config->get('page-views.enabled', true)) {
            return false;
        }

        if ($request->method() !== 'GET') {
            return false;
        }

        $trackable = (array) $this->config->get('page-views.trackable_status_codes', [200, 304]);

        if (! in_array($response->getStatusCode(), $trackable, true)) {
            return false;
        }

        if ($this->isExcludedPath($request->path())) {
            return false;
        }

        if ($this->botDetector->isBot($request->userAgent() ?? '')) {
            return false;
        }

        if ($this->honoursDoNotTrack($request)) {
            return false;
        }

        foreach ($this->conditions as $condition) {
            if (! $condition($request, $response)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesFor(Request $request): array
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';
        $referrer = $request->header('referer');
        $now = now();

        return [
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $request->route()?->getName(),
            'referrer' => $this->truncate($referrer, 1024),
            'referrer_host' => $this->referrerParser->host($referrer),
            'browser' => $this->userAgents->browser($userAgent),
            'platform' => $this->userAgents->platform($userAgent),
            'ip_anon' => $this->ipAnonymizer->anonymize($ip),
            'visitor_hash' => $this->visitorHasher->hash($ip, $userAgent, $now->toDateString()),
            'country' => $this->countryResolver->resolve($ip),
            'viewed_at' => $now,
            ...$this->utmParameters($request),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function utmParameters(Request $request): array
    {
        if (! $this->config->get('page-views.track_utm', true)) {
            return [];
        }

        return [
            'utm_source' => $this->truncate($request->query('utm_source'), 255),
            'utm_medium' => $this->truncate($request->query('utm_medium'), 255),
            'utm_campaign' => $this->truncate($request->query('utm_campaign'), 255),
        ];
    }

    private function honoursDoNotTrack(Request $request): bool
    {
        if (! $this->config->get('page-views.respect_do_not_track', true)) {
            return false;
        }

        return $request->header('DNT') === '1' || $request->header('Sec-GPC') === '1';
    }

    private function isExcludedPath(string $path): bool
    {
        $pattern = $this->config->get('page-views.excluded_paths');

        if (! is_string($pattern) || $pattern === '') {
            return false;
        }

        return (bool) preg_match($pattern, $path);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function dispatchJob(array $attributes): void
    {
        $attributes['viewed_at'] = $attributes['viewed_at']->toDateTimeString();

        RecordPageView::dispatch($attributes)
            ->onConnection($this->config->get('page-views.queue.connection'))
            ->onQueue($this->config->get('page-views.queue.queue'));
    }

    private function truncate(mixed $value, int $length): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
