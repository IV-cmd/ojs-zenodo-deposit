<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes\ojs;

use APP\plugins\generic\zenodoDeposit\ZenodoDepositPlugin;
use APP\plugins\generic\zenodoDeposit\classes\DepositSettings;
use APP\plugins\generic\zenodoDeposit\classes\SettingsValidator;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorPost;

/**
 * Per-journal settings form (journal managers only, via Plugin::manage).
 *
 * The Zenodo token is write-only: it is never placed in $_data, so it can
 * never be rendered into HTML or returned in a JSON response. The form shows
 * "configured / not configured" instead.
 */
final class ZenodoDepositSettingsForm extends Form
{
    public function __construct(
        private ZenodoDepositPlugin $plugin,
        private int $contextId
    ) {
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $validator = new SettingsValidator();

        $this->addCheck(
            new FormValidatorCustom(
                $this,
                'zenodo_enabled',
                'optional',
                'plugins.generic.zenodoDeposit.settings.tokenRequired',
                fn () => $validator->validate(
                    [
                        'zenodo_enabled' => $this->getData('zenodo_enabled'),
                        'zenodo_token' => $this->getData('zenodo_token'),
                    ],
                    $this->hasStoredToken()
                ) === []
            )
        );

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData(): void
    {
        $stored = ZenodoPluginSettings::forContext($this->contextId);
        $settings = DepositSettings::fromArray($stored);

        // zenodo_token is deliberately absent: write-only, never re-rendered.
        $this->_data = [
            'zenodo_enabled' => $settings->enabled,
            'zenodo_sandbox' => $settings->sandbox,
            'zenodo_deposit_datasets' => $settings->depositDatasets,
            'zenodo_token_configured' => $this->hasStoredToken(),
        ];
    }

    public function readInputData(): void
    {
        $this->readUserVars([
            'zenodo_enabled',
            'zenodo_sandbox',
            'zenodo_deposit_datasets',
            'zenodo_token',
        ]);
    }

    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pluginName', $this->plugin->getName());
        $templateMgr->assign(
            'zenodoSandboxWarning',
            (new SettingsValidator())->sandboxWarning([
                'zenodo_enabled' => $this->getData('zenodo_enabled'),
                'zenodo_sandbox' => $this->getData('zenodo_sandbox'),
            ])
        );

        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs): mixed
    {
        $settings = DepositSettings::fromArray([
            'zenodo_enabled' => $this->getData('zenodo_enabled'),
            'zenodo_sandbox' => $this->getData('zenodo_sandbox'),
            'zenodo_deposit_datasets' => $this->getData('zenodo_deposit_datasets'),
        ]);

        $this->plugin->updateSetting(
            $this->contextId,
            'zenodo_enabled',
            $settings->enabled ? '1' : '0'
        );
        $this->plugin->updateSetting(
            $this->contextId,
            'zenodo_sandbox',
            $settings->sandbox ? '1' : '0'
        );
        $this->plugin->updateSetting(
            $this->contextId,
            'zenodo_deposit_datasets',
            $settings->depositDatasets ? '1' : '0'
        );

        // The sandbox toggle drives the client's mode + base URL. Mock mode is
        // reachable only by writing plugin settings directly (dev use).
        $this->plugin->updateSetting(
            $this->contextId,
            'zenodo_mode',
            $settings->sandbox ? 'sandbox' : 'live'
        );
        $this->plugin->updateSetting(
            $this->contextId,
            'zenodo_base_url',
            $settings->sandbox ? 'https://sandbox.zenodo.org' : 'https://zenodo.org'
        );

        $token = $this->getData('zenodo_token');

        if (is_string($token) && trim($token) !== '') {
            $this->plugin->updateSetting($this->contextId, 'zenodo_token', trim($token));
        }

        return parent::execute(...$functionArgs);
    }

    private function hasStoredToken(): bool
    {
        $stored = ZenodoPluginSettings::forContext($this->contextId);
        $token = $stored['zenodo_token'] ?? null;

        return is_string($token) && trim($token) !== '';
    }
}
