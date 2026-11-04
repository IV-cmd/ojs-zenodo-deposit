<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

use APP\plugins\generic\zenodoDeposit\classes\http\GuzzleTransport;
use APP\plugins\generic\zenodoDeposit\classes\http\HttpTransport;

final class ZenodoClientFactory
{
    public static function make(
        ZenodoConfig $config,
        ?HttpTransport $transport = null
    ): ZenodoClientInterface {
        if ($config->mode === ZenodoConfig::MODE_MOCK) {
            return new MockZenodoClient();
        }

        if (!in_array($config->mode, [ZenodoConfig::MODE_SANDBOX, ZenodoConfig::MODE_LIVE], true)) {
            throw new ZenodoConfigException('Unknown Zenodo mode.');
        }

        if ($config->token === '') {
            throw new ZenodoConfigException(
                "Zenodo {$config->mode} mode requires zenodo_token to be configured."
            );
        }

        if ($config->mode === ZenodoConfig::MODE_SANDBOX && self::isProductionHost($config->baseUrl)) {
            throw new ZenodoConfigException('Zenodo sandbox mode must not point at production.');
        }

        if ($transport === null) {
            if (!class_exists(\GuzzleHttp\Client::class)) {
                throw new ZenodoConfigException('Guzzle is required to create the default Zenodo HTTP transport.');
            }

            $transport = new GuzzleTransport(new \GuzzleHttp\Client([
                'connect_timeout' => 10,
                'timeout' => 30,
            ]));
        }

        return new ZenodoClient($config->baseUrl, $config->token, $transport);
    }

    private static function isProductionHost(string $baseUrl): bool
    {
        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (!is_string($host)) {
            return false;
        }

        $normalizedHost = rtrim(strtolower($host), '.');

        return in_array($normalizedHost, ['zenodo.org', 'www.zenodo.org'], true);
    }
}
