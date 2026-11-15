<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Token-secrecy boundary for the M4 settings form, checked statically because
 * the Form class needs an OJS bootstrap: the token may be written but must
 * never be part of initData (what the form renders) or the template's value
 * variables.
 */
class SettingsFormBoundaryTest extends TestCase
{
    private string $formSource;
    private string $templateSource;

    protected function setUp(): void
    {
        $root = dirname(__DIR__);
        $this->formSource = (string) file_get_contents(
            $root . '/classes/ojs/ZenodoDepositSettingsForm.php'
        );
        $this->templateSource = (string) file_get_contents(
            $root . '/templates/settingsForm.tpl'
        );
    }

    public function testInitDataNeverContainsTheToken(): void
    {
        $start = strpos($this->formSource, 'function initData');
        $end = strpos($this->formSource, 'function readInputData');
        self::assertIsInt($start);
        self::assertIsInt($end);

        $initData = substr($this->formSource, $start, $end - $start);

        // initData must expose only the configured/not-configured boolean.
        self::assertStringNotContainsString("'zenodo_token' =>", $initData);
        self::assertStringContainsString('zenodo_token_configured', $initData);
    }

    public function testTemplateNeverRendersAStoredTokenValue(): void
    {
        // The field is password-type with no value= assignment: the browser
        // must never receive the stored secret back.
        self::assertMatchesRegularExpression('/id="zenodo_token"[^}]*password=true/', $this->templateSource);
        self::assertStringNotContainsString('value=$zenodo_token', $this->templateSource);
    }
}
