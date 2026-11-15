<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\SettingsValidator;
use PHPUnit\Framework\TestCase;

class SettingsValidatorTest extends TestCase
{
    public function testDisabledNeedsNoToken(): void
    {
        $errors = (new SettingsValidator())->validate(['zenodo_enabled' => '0'], false);

        self::assertSame([], $errors);
    }

    public function testEnabledWithoutAnyTokenFails(): void
    {
        $errors = (new SettingsValidator())->validate(['zenodo_enabled' => '1'], false);

        self::assertSame(['manager.plugins.zenodoDeposit.settings.tokenRequired'], $errors);
    }

    public function testEnabledWithStoredTokenPasses(): void
    {
        $errors = (new SettingsValidator())->validate(['zenodo_enabled' => '1'], true);

        self::assertSame([], $errors);
    }

    public function testEnabledWithFreshTokenPasses(): void
    {
        $errors = (new SettingsValidator())->validate(
            ['zenodo_enabled' => '1', 'zenodo_token' => 'abc'],
            false
        );

        self::assertSame([], $errors);
    }

    public function testWhitespaceTokenDoesNotCount(): void
    {
        $errors = (new SettingsValidator())->validate(
            ['zenodo_enabled' => '1', 'zenodo_token' => '   '],
            false
        );

        self::assertSame(['manager.plugins.zenodoDeposit.settings.tokenRequired'], $errors);
    }

    public function testSandboxWarningOnlyWhenEnabledAndSandbox(): void
    {
        $validator = new SettingsValidator();

        self::assertTrue(
            $validator->sandboxWarning(['zenodo_enabled' => '1', 'zenodo_sandbox' => '1'])
        );
        self::assertFalse(
            $validator->sandboxWarning(['zenodo_enabled' => '0', 'zenodo_sandbox' => '1'])
        );
        self::assertFalse(
            $validator->sandboxWarning(['zenodo_enabled' => '1', 'zenodo_sandbox' => '0'])
        );
    }
}
