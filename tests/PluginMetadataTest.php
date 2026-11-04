<?php

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\ZenodoDepositPlugin;
use PHPUnit\Framework\TestCase;

class PluginMetadataTest extends TestCase
{
    public function testVersionMetadataAndLoaderConvention(): void
    {
        $version = simplexml_load_file(dirname(__DIR__) . '/version.xml');

        self::assertNotFalse($version);
        self::assertSame('zenodoDeposit', (string) $version->application);
        self::assertSame('plugins.generic', (string) $version->type);
        self::assertSame('ZenodoDepositPlugin', (string) $version->class);
        self::assertSame(
            'APP\\plugins\\generic\\' . (string) $version->application . '\\'
                . ucfirst((string) $version->application) . 'Plugin',
            ZenodoDepositPlugin::class
        );
    }

    public function testIndexAndEnglishLocaleArePresent(): void
    {
        $plugin = require dirname(__DIR__) . '/index.php';
        $locale = file_get_contents(dirname(__DIR__) . '/locale/en/locale.po');

        self::assertInstanceOf(ZenodoDepositPlugin::class, $plugin);
        self::assertIsString($locale);
        self::assertStringContainsString('msgid "plugins.generic.zenodoDeposit.displayName"', $locale);
        self::assertStringContainsString('msgid "plugins.generic.zenodoDeposit.description"', $locale);
    }
}
