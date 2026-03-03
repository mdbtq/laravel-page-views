<?php

namespace Mdbtq\PageViews\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mdbtq\PageViews\Models\PageView;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Run after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($request->method() !== 'GET') {
            return;
        }

        if ($response->getStatusCode() !== 200) {
            return;
        }

        $path = $request->path();

        if ($this->isExcludedPath($path)) {
            return;
        }

        $userAgent = $request->userAgent() ?? '';

        if ($this->isBot($userAgent)) {
            return;
        }

        try {
            $ip = $request->ip();

            PageView::create([
                'path' => '/' . ltrim($path, '/'),
                'referrer' => $this->truncate($request->header('referer'), 1024),
                'user_agent' => $this->truncate($userAgent, 1024) ?: null,
                'ip_anon' => $this->anonymizeIp($ip),
                'country' => $this->resolveCountry($ip),
                'viewed_at' => now(),
            ]);
        } catch (\Throwable) {
            // Silently fail — tracking should never break the site
        }
    }

    private function anonymizeIp(?string $ip): string
    {
        if (! config('page-views.anonymize_ip', true)) {
            return $ip ?? '0.0.0.0';
        }

        if ($ip === null) {
            return '0.0.0.0';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $expanded = inet_ntop(inet_pton($ip));
            $groups = explode(':', $expanded);

            for ($i = 3; $i < 8; $i++) {
                $groups[$i] = '0';
            }

            return implode(':', $groups);
        }

        return '0.0.0.0';
    }

    private function isBot(string $userAgent): bool
    {
        $lower = strtolower($userAgent);
        $signatures = config('page-views.bot_signatures', []);

        foreach ($signatures as $sig) {
            if (str_contains($lower, $sig)) {
                return true;
            }
        }

        return false;
    }

    private function isExcludedPath(string $path): bool
    {
        $pattern = config('page-views.excluded_paths');

        if (! $pattern) {
            return false;
        }

        return (bool) preg_match($pattern, $path);
    }

    private function resolveCountry(?string $ip): ?string
    {
        if (! function_exists('geoip')) {
            return null;
        }

        try {
            $location = geoip($ip);

            return ($location->default ?? true) ? null : ($location->iso_code ?: null);
        } catch (\Throwable) {
            return null;
        }
    }

    private function truncate(?string $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
