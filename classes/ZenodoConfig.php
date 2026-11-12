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

    public const DEFAULT_MOCK_SCENARIO = MockZenodoClient::SCENARIO_SUCCESS;

    public const DEFAULT_MOCK_STEP_DELAY = 0;

    private function __construct(
        public string $mode,
        public string $baseUrl,
        #[\SensitiveParameter] public string $token,
        public string $mockScenario,
        public int $mockStepDelay
    ) {
    }

    public static function fromArray(array $settings): self
    {
        $mode = $settings['zenodo_mode'] ?? self::DEFAULT_MODE;
        $baseUrl = $settings['zenodo_base_url'] ?? self::DEFAULT_BASE_URL;
        $token = $settings['zenodo_token'] ?? self::DEFAULT_TOKEN;
        $mockScenario = $settings['zenodo_mock_scenario'] ?? self::DEFAULT_MOCK_SCENARIO;
        $mockStepDelay = $settings['zenodo_mock_step_delay'] ?? self::DEFAULT_MOCK_STEP_DELAY;

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

        if (
            !is_string($mockScenario)
            || !in_array($mockScenario, [
                MockZenodoClient::SCENARIO_SUCCESS,
                MockZenodoClient::SCENARIO_OUTAGE,
                MockZenodoClient::SCENARIO_VALIDATION_ERROR,
            ], true)
        ) {
            throw new ZenodoConfigException('Unknown Zenodo mock scenario.');
        }

        if (is_string($mockStepDelay) && ctype_digit($mockStepDelay)) {
            $mockStepDelay = (int) $mockStepDelay;
        }

        if (!is_int($mockStepDelay) || $mockStepDelay < 0) {
            throw new ZenodoConfigException('Zenodo mock step delay must be a non-negative integer.');
        }

        return new self($mode, $baseUrl, $token, $mockScenario, $mockStepDelay);
    }
}
