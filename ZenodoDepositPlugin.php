<?php

/**
 * Generic plugin skeleton for Zenodo Deposit.
 *
 * @license MIT
 */

namespace APP\plugins\generic\zenodoDeposit;

use APP\plugins\generic\zenodoDeposit\classes\ojs\ZenodoDepositSchemaMigration;
use Illuminate\Database\Migrations\Migration;
use PKP\plugins\GenericPlugin;

class ZenodoDepositPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null): bool
    {
        return parent::register($category, $path, $mainContextId);
    }

    public function getDisplayName(): string
    {
        return __('plugins.generic.zenodoDeposit.displayName');
    }

    public function getDescription(): string
    {
        return __('plugins.generic.zenodoDeposit.description');
    }

    public function getInstallMigration(): ?Migration
    {
        return new ZenodoDepositSchemaMigration();
    }
}
