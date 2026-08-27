<?php

namespace Mdbtq\PageViews\Support;

/**
 * Parses a single access log line into its constituent fields.
 *
 * Supports the two formats that cover almost every deployment: the NCSA
 * combined format emitted by nginx and Apache by default, and JSON, which
 * is what most CDNs and structured log pipelines produce.
 */
class LogLineParser
{
    /**
     * NCSA combined:
     * host - user [date] "METHOD path proto" status bytes "referer" "agent"
     */
    private const COMBINED = '/^(?<ip>\S+) \S+ \S+ \[(?<time>[^\]]+)\] "(?<method>[A-Z]+) (?<target>\S+)(?: (?<proto>\S+))?" (?<status>\d{3}) (?<bytes>\S+)(?: "(?<referer>[^"]*)" "(?<agent>[^"]*)")?/';

    /**
     * Field names accepted in JSON log lines, in order of preference. CDNs
     * disagree on naming, so each logical field has several aliases.
     *
     * @var array<string, array<int, string>>
     */
    private const JSON_KEYS = [
        'ip' => ['remote_addr', 'client_ip', 'ClientIP', 'ip', 'c-ip'],
        'time' => ['time_local', 'timestamp', 'time', 'EdgeStartTimestamp', '@timestamp'],
        'method' => ['request_method', 'method', 'ClientRequestMethod', 'cs-method'],
        'target' => ['request_uri', 'uri', 'ClientRequestURI', 'cs-uri-stem', 'path'],
        'status' => ['status', 'EdgeResponseStatus', 'sc-status', 'response_status'],
        'referer' => ['http_referer', 'referer', 'referrer', 'ClientRequestReferer', 'cs(Referer)'],
        'agent' => ['http_user_agent', 'user_agent', 'ClientRequestUserAgent', 'cs(User-Agent)'],
    ];

    /**
     * @return array{ip: string, time: string, method: string, target: string, status: int, referer: string|null, agent: string}|null
     */
    public function parse(string $line): ?array
    {
        $line = trim($line);

        if ($line === '') {
            return null;
        }

        return str_starts_with($line, '{')
            ? $this->parseJson($line)
            : $this->parseCombined($line);
    }

    /**
     * @return array{ip: string, time: string, method: string, target: string, status: int, referer: string|null, agent: string}|null
     */
    private function parseCombined(string $line): ?array
    {
        if (preg_match(self::COMBINED, $line, $matches) !== 1) {
            return null;
        }

        return [
            'ip' => $matches['ip'],
            'time' => $matches['time'],
            'method' => $matches['method'],
            'target' => $matches['target'],
            'status' => (int) $matches['status'],
            'referer' => $this->emptyToNull($matches['referer'] ?? null),
            'agent' => $matches['agent'] ?? '',
        ];
    }

    /**
     * @return array{ip: string, time: string, method: string, target: string, status: int, referer: string|null, agent: string}|null
     */
    private function parseJson(string $line): ?array
    {
        $decoded = json_decode($line, true);

        if (! is_array($decoded)) {
            return null;
        }

        $target = $this->pick($decoded, 'target');
        $status = $this->pick($decoded, 'status');

        if ($target === null || $status === null) {
            return null;
        }

        return [
            'ip' => $this->pick($decoded, 'ip') ?? '',
            'time' => $this->pick($decoded, 'time') ?? '',
            'method' => strtoupper($this->pick($decoded, 'method') ?? 'GET'),
            'target' => $target,
            'status' => (int) $status,
            'referer' => $this->emptyToNull($this->pick($decoded, 'referer')),
            'agent' => $this->pick($decoded, 'agent') ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pick(array $data, string $field): ?string
    {
        foreach (self::JSON_KEYS[$field] as $key) {
            if (isset($data[$key]) && (is_string($data[$key]) || is_int($data[$key]))) {
                $value = (string) $data[$key];

                if ($value !== '' && $value !== '-') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function emptyToNull(?string $value): ?string
    {
        return $value === null || $value === '' || $value === '-' ? null : $value;
    }
}
