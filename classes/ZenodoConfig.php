<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

final readonly class ZenodoConfig
{
    public const MODE_MOCK = 'mock';

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_LIVE = 'live';

    public const DEFAULT_MODE = 'mock';

    public const DEFAULT_BASE_URL = 'https://sandbox.zenodo.org';

    public const DEFAULT_TOKEN = '';

    private function __construct(
        public string $mode,
        public string $baseUrl,
        #[\SensitiveParameter] public string $token
    ) {
    }

    public static function fromArray(array $settings): self
    {
        $mode = $settings['zenodo_mode'] ?? self::DEFAULT_MODE;
        $baseUrl = $settings['zenodo_base_url'] ?? self::DEFAULT_BASE_URL;
        $token = $settings['zenodo_token'] ?? self::DEFAULT_TOKEN;

        $validModes = [
            self::MODE_MOCK,
            self::MODE_SANDBOX,
            self::MODE_LIVE,
        ];

        if (!is_string($mode) || !in_array($mode, $validModes, true)) {
            throw new ZenodoConfigException('Unknown Zenodo mode.');
        }

        if (!is_string($baseUrl)) {
            throw new ZenodoConfigException('Zenodo base URL must be a valid HTTPS URL.');
        }

        $parsedBaseUrl = parse_url($baseUrl);

        if (
            !is_array($parsedBaseUrl)
            || strtolower($parsedBaseUrl['scheme'] ?? '') !== 'https'
            || !is_string($parsedBaseUrl['host'] ?? null)
            || $parsedBaseUrl['host'] === ''
        ) {
            throw new ZenodoConfigException('Zenodo base URL must be a valid HTTPS URL.');
        }

        if (!is_string($token)) {
            throw new ZenodoConfigException('Zenodo token must be a string.');
        }

        return new self($mode, $baseUrl, $token);
    }
}
