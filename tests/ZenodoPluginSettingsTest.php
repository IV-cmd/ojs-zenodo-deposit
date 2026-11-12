<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\ojs\ZenodoPluginSettings;
use PHPUnit\Framework\TestCase;
use PKP\db\PluginSettingsDAO;

class ZenodoPluginSettingsTest extends TestCase
{
    public function testReadsCurrentContextSettingsAndOnlyReturnsKnownNames(): void
    {
        PluginSettingsDAO::$settings = [
            'zenodo_mode' => 'mock',
            'zenodo_mock_scenario' => 'success',
            'unrelated_setting' => 'ignored',
        ];

        $settings = ZenodoPluginSettings::forContext(1);

        self::assertSame('mock', $settings['zenodo_mode']);
        self::assertSame('success', $settings['zenodo_mock_scenario']);
        self::assertArrayNotHasKey('unrelated_setting', $settings);
    }
}
