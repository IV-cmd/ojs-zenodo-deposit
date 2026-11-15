<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

/**
 * Pure validation + persistence rules for the per-journal settings form.
 *
 * Kept OJS-free so the rules are unit-testable: the Form subclass delegates
 * here. The token is write-only — it is never part of the form's data, so it
 * can never be rendered back into HTML or a JSON response.
 */
final class SettingsValidator
{
    /**
     * @param array<string, mixed> $input  user-submitted booleans
     * @param bool                 $hasStoredToken a token is already stored
     * @return list<string> error message keys; empty means valid
     */
    public function validate(array $input, bool $hasStoredToken): array
    {
        $settings = DepositSettings::fromArray($input);

        $errors = [];

        if ($settings->enabled && !$hasStoredToken && !$this->hasNewToken($input)) {
            $errors[] = 'manager.plugins.zenodoDeposit.settings.tokenRequired';
        }

        return $errors;
    }

    /**
     * Warn-only misconfig signal: enabled + sandbox on means "deposits go to
     * the sandbox in production". Displayed in the form; never blocks save.
     */
    public function sandboxWarning(array $input): bool
    {
        $settings = DepositSettings::fromArray($input);

        return $settings->enabled && $settings->sandbox;
    }

    private function hasNewToken(array $input): bool
    {
        $token = $input['zenodo_token'] ?? null;

        return is_string($token) && trim($token) !== '';
    }
}
