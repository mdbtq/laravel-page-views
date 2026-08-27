<?php

namespace Mdbtq\PageViews\Support;

class IpAnonymizer
{
    /**
     * Number of trailing IPv6 groups zeroed out, leaving a /48 prefix.
     */
    private const IPV6_ZEROED_GROUPS = 5;

    public function anonymize(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return '0.0.0.0';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->anonymizeIpv6($ip);
        }

        return '0.0.0.0';
    }

    /**
     * Zero the trailing groups of an IPv6 address.
     *
     * inet_ntop() returns the compressed form, so the address is expanded
     * from its binary representation rather than split on ":".
     */
    private function anonymizeIpv6(string $ip): string
    {
        $packed = inet_pton($ip);

        if ($packed === false) {
            return '0.0.0.0';
        }

        $groups = str_split(bin2hex($packed), 4);
        $keep = count($groups) - self::IPV6_ZEROED_GROUPS;

        array_splice($groups, $keep, self::IPV6_ZEROED_GROUPS, array_fill(0, self::IPV6_ZEROED_GROUPS, '0000'));

        $anonymized = inet_ntop(hex2bin(implode('', $groups)));

        return $anonymized === false ? '0.0.0.0' : $anonymized;
    }
}
