<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\DepositSettings;
use PHPUnit\Framework\TestCase;

class DepositSettingsTest extends TestCase
{
    public function testDefaultsOptOutOfDepositsAndPreferSandbox(): void
    {
        $settings = DepositSettings::fromArray([]);

        self::assertFalse($settings->enabled);
        self::assertTrue($settings->sandbox);
        self::assertTrue($settings->depositDatasets);
    }

    public function testReadsStoredStrings(): void
    {
        $settings = DepositSettings::fromArray([
            'zenodo_enabled' => '1',
            'zenodo_sandbox' => '0',
            'zenodo_deposit_datasets' => '0',
        ]);

        self::assertTrue($settings->enabled);
        self::assertFalse($settings->sandbox);
        self::assertFalse($settings->depositDatasets);
    }

    public function testBooleansAndInts(): void
    {
        $settings = DepositSettings::fromArray([
            'zenodo_enabled' => true,
            'zenodo_sandbox' => 0,
        ]);

        self::assertTrue($settings->enabled);
        self::assertFalse($settings->sandbox);
    }
}
