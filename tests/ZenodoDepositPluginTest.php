<?php

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\ZenodoDepositPlugin;
use PHPUnit\Framework\TestCase;
use PKP\plugins\Hook;

class ZenodoDepositPluginTest extends TestCase
{
    public function testPluginRegistrationAndLocalizedMetadata(): void
    {
        $plugin = new ZenodoDepositPlugin();

        self::assertTrue($plugin->register('generic', 'plugins/generic/zenodoDeposit'));
        self::assertSame('generic', $plugin->getCategory());
        self::assertSame('plugins/generic/zenodoDeposit', $plugin->getPluginPath());
        self::assertSame('zenododepositplugin', $plugin->getName());
        self::assertSame('plugins.generic.zenodoDeposit.displayName', $plugin->getDisplayName());
        self::assertSame('plugins.generic.zenodoDeposit.description', $plugin->getDescription());
        self::assertCount(1, Hook::callbacksFor('Publication::publish'));
    }
}
