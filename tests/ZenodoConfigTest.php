<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\MockZenodoClient;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfig;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfigException;
use PHPUnit\Framework\TestCase;

class ZenodoConfigTest extends TestCase
{
    public function testDefaultsUseMockModeAndSandboxBaseUrl(): void
    {
        $config = ZenodoConfig::fromArray([]);

        self::assertSame('mock', $config->mode);
        self::assertSame('https://sandbox.zenodo.org', $config->baseUrl);
        self::assertSame('', $config->token);
        self::assertSame(MockZenodoClient::SCENARIO_SUCCESS, $config->mockScenario);
        self::assertSame(0, $config->mockStepDelay);
    }

    public function testRejectsUnknownMode(): void
    {
        $this->expectException(ZenodoConfigException::class);
        ZenodoConfig::fromArray(['zenodo_mode' => 'unknown']);
    }

    public function testRejectsNonHttpsBaseUrl(): void
    {
        $this->expectException(ZenodoConfigException::class);
        ZenodoConfig::fromArray(['zenodo_base_url' => 'http://sandbox.zenodo.org']);
    }

    public function testRejectsBaseUrlWithoutHost(): void
    {
        $this->expectException(ZenodoConfigException::class);
        ZenodoConfig::fromArray(['zenodo_base_url' => 'https:///invalid']);
    }

    public function testRejectsUnknownMockScenario(): void
    {
        $this->expectException(ZenodoConfigException::class);
        ZenodoConfig::fromArray(['zenodo_mock_scenario' => 'unknown']);
    }

    public function testRejectsInvalidMockDelay(): void
    {
        $this->expectException(ZenodoConfigException::class);
        ZenodoConfig::fromArray(['zenodo_mock_step_delay' => '-1']);
    }
}
