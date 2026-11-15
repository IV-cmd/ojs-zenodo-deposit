<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes\ojs;

use APP\plugins\generic\zenodoDeposit\ZenodoDepositPlugin;
use PKP\db\DAORegistry;

final class ZenodoPluginSettings
{
    private const SETTING_NAMES = [
        'zenodo_enabled',
        'zenodo_sandbox',
        'zenodo_deposit_datasets',
        'zenodo_mode',
        'zenodo_base_url',
        'zenodo_token',
        'zenodo_mock_scenario',
        'zenodo_mock_step_delay',
        'zenodo_keywords',
        'zenodo_communities',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function forContext(int $contextId): array
    {
        $settingsDao = DAORegistry::getDAO('PluginSettingsDAO');
        $storedSettings = $settingsDao->getPluginSettings(
            $contextId,
            ZenodoDepositPlugin::PLUGIN_NAME
        );
        $settings = [];

        foreach (self::SETTING_NAMES as $settingName) {
            $settings[$settingName] = $storedSettings[$settingName] ?? null;
        }

        return $settings;
    }
}
