<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

/**
 * Pure reader for the per-journal deposit settings added in M4.
 *
 * The OJS form writes these keys through ZenodoPluginSettings; this class
 * turns them into typed booleans so the plugin class and the form share one
 * interpretation. No OJS imports: unit tests exercise this without a bootstrap.
 */
final readonly class DepositSettings
{
    public const DEFAULT_SANDBOX = true;
    public const DEFAULT_ENABLED = false;
    public const DEFAULT_DEPOSIT_DATASETS = true;

    private function __construct(
        public bool $enabled,
        public bool $sandbox,
        public bool $depositDatasets
    ) {
    }

    /**
     * @param array<string, mixed> $settings keys from ZenodoPluginSettings
     */
    public static function fromArray(array $settings): self
    {
        return new self(
            self::toBool($settings['zenodo_enabled'] ?? null, self::DEFAULT_ENABLED),
            self::toBool($settings['zenodo_sandbox'] ?? null, self::DEFAULT_SANDBOX),
            self::toBool($settings['zenodo_deposit_datasets'] ?? null, self::DEFAULT_DEPOSIT_DATASETS),
        );
    }

    private static function toBool(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return $default;
    }
}
