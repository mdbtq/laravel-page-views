<?php

namespace Mdbtq\PageViews\Support;

use Illuminate\Contracts\Config\Repository as Config;

class BotDetector
{
    public function __construct(private readonly Config $config) {}

    public function isBot(string $userAgent): bool
    {
        if (trim($userAgent) === '') {
            return (bool) $this->config->get('page-views.empty_user_agent_is_bot', true);
        }

        $lower = strtolower($userAgent);

        foreach ((array) $this->config->get('page-views.bot_signatures', []) as $signature) {
            if ($signature !== '' && str_contains($lower, strtolower((string) $signature))) {
                return true;
            }
        }

        return false;
    }
}
