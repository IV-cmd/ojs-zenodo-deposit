<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

final class LicenseMap
{
    private const URL_TO_ID = [
        '/licenses/by/4.0' => 'cc-by-4.0',
        '/licenses/by-sa/4.0' => 'cc-by-sa-4.0',
        '/licenses/by-nd/4.0' => 'cc-by-nd-4.0',
        '/licenses/by-nc/4.0' => 'cc-by-nc-4.0',
        '/licenses/by-nc-sa/4.0' => 'cc-by-nc-sa-4.0',
        '/licenses/by-nc-nd/4.0' => 'cc-by-nc-nd-4.0',
        '/publicdomain/zero/1.0' => 'cc0-1.0',
    ];

    public static function toZenodoId(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = parse_url(trim($url));

        if (!is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        $port = $parts['port'] ?? null;

        if (
            !in_array($scheme, ['http', 'https'], true)
            || preg_replace('/^www\./i', '', $host) !== 'creativecommons.org'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ($port !== null && $port !== ($scheme === 'https' ? 443 : 80))
        ) {
            return null;
        }

        $path = strtolower(rtrim($parts['path'] ?? '', '/'));
        $path = preg_replace('~/(?:legalcode|deed\.[a-z]{2}(?:-[a-z]{2})?)$~', '', $path);
        $path = rtrim($path ?? '', '/');

        return self::URL_TO_ID[$path] ?? null;
    }
}
