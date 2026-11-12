<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\MockZenodoClient;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoClient;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoClientFactory;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfig;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfigException;
use PHPUnit\Framework\TestCase;
use ZenodoDeposit\Tests\Support\FakeTransport;

class ZenodoClientFactoryTest extends TestCase
{
    public function testMockModeCreatesMockClient(): void
    {
        $client = ZenodoClientFactory::make(ZenodoConfig::fromArray([]));

        self::assertInstanceOf(MockZenodoClient::class, $client);
    }

    public function testMockModeUsesConfiguredScenarioAndDelay(): void
    {
        $config = ZenodoConfig::fromArray([
            'zenodo_mock_scenario' => MockZenodoClient::SCENARIO_VALIDATION_ERROR,
            'zenodo_mock_step_delay' => '0',
        ]);
        $client = ZenodoClientFactory::make($config);

        try {
            $client->createDeposition([]);
            self::fail('Expected the configured validation scenario.');
        } catch (\APP\plugins\generic\zenodoDeposit\classes\ZenodoApiException $exception) {
            self::assertSame(422, $exception->getStatusCode());
        }
    }

    public function testSandboxAndLiveModesCreateRestClientWithSuppliedTransport(): void
    {
        foreach ([ZenodoConfig::MODE_SANDBOX, ZenodoConfig::MODE_LIVE] as $mode) {
            $config = ZenodoConfig::fromArray([
                'zenodo_mode' => $mode,
                'zenodo_token' => 'test-token',
            ]);

            $client = ZenodoClientFactory::make($config, new FakeTransport());

            self::assertInstanceOf(ZenodoClient::class, $client);
        }
    }

    public function testDefaultTransportCreatesGuzzleRestClient(): void
    {
        $config = ZenodoConfig::fromArray([
            'zenodo_mode' => ZenodoConfig::MODE_SANDBOX,
            'zenodo_token' => 'test-token',
        ]);

        self::assertInstanceOf(ZenodoClient::class, ZenodoClientFactory::make($config));
    }

    public function testNetworkModesRequireTokenWithoutEchoingIt(): void
    {
        foreach ([ZenodoConfig::MODE_SANDBOX, ZenodoConfig::MODE_LIVE] as $mode) {
            $config = ZenodoConfig::fromArray([
                'zenodo_mode' => $mode,
                'zenodo_token' => '',
            ]);

            try {
                ZenodoClientFactory::make($config, new FakeTransport());
                self::fail('Expected a configuration exception.');
            } catch (ZenodoConfigException $exception) {
                self::assertStringContainsString($mode, $exception->getMessage());
                self::assertStringContainsString('zenodo_token', $exception->getMessage());
                self::assertStringNotContainsString('test-token', $exception->getMessage());
            }
        }
    }

    public function testSandboxModeRefusesProductionHosts(): void
    {
        foreach (['https://zenodo.org', 'https://www.zenodo.org'] as $baseUrl) {
            $config = ZenodoConfig::fromArray([
                'zenodo_mode' => ZenodoConfig::MODE_SANDBOX,
                'zenodo_base_url' => $baseUrl,
                'zenodo_token' => 'test-token',
            ]);

            $exception = null;

            try {
                ZenodoClientFactory::make($config, new FakeTransport());
            } catch (ZenodoConfigException $caught) {
                $exception = $caught;
            }

            self::assertInstanceOf(ZenodoConfigException::class, $exception);
            self::assertStringContainsString('sandbox mode must not point at production', $exception->getMessage());
        }
    }
}
