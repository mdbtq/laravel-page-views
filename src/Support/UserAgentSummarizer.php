<?php

namespace Mdbtq\PageViews\Support;

/**
 * Reduces a User-Agent string to a coarse browser and platform label.
 *
 * Storing the raw header alongside the anonymized IP would make the daily
 * visitor hash reproducible from the table itself, defeating the rotation.
 * Only these low-cardinality labels are kept.
 */
class UserAgentSummarizer
{
    /**
     * Ordered browser signatures. The first match wins, so derivatives are
     * listed before the engines they are built on.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const BROWSERS = [
        ['edg/', 'Edge'],
        ['opr/', 'Opera'],
        ['opera', 'Opera'],
        ['vivaldi', 'Vivaldi'],
        ['brave', 'Brave'],
        ['samsungbrowser', 'Samsung Internet'],
        ['firefox', 'Firefox'],
        ['chrome', 'Chrome'],
        ['chromium', 'Chrome'],
        ['safari', 'Safari'],
    ];

    /**
     * @var array<int, array{0: string, 1: string}>
     */
    private const PLATFORMS = [
        ['iphone', 'iOS'],
        ['ipad', 'iPadOS'],
        ['android', 'Android'],
        ['windows', 'Windows'],
        ['mac os x', 'macOS'],
        ['macintosh', 'macOS'],
        ['cros', 'ChromeOS'],
        ['linux', 'Linux'],
    ];

    public function browser(string $userAgent): ?string
    {
        return $this->match($userAgent, self::BROWSERS);
    }

    public function platform(string $userAgent): ?string
    {
        return $this->match($userAgent, self::PLATFORMS);
    }

    /**
     * @param  array<int, array{0: string, 1: string}>  $signatures
     */
    private function match(string $userAgent, array $signatures): ?string
    {
        $lower = strtolower(trim($userAgent));

        if ($lower === '') {
            return null;
        }

        foreach ($signatures as [$needle, $label]) {
            if (str_contains($lower, $needle)) {
                return $label;
            }
        }

        return null;
    }
}
