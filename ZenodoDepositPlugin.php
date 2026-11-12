<?php

/**
 * Generic plugin skeleton for Zenodo Deposit.
 *
 * @license MIT
 */

namespace APP\plugins\generic\zenodoDeposit;

use APP\plugins\generic\zenodoDeposit\classes\PublishHookHandler;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfig;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfigException;
use APP\plugins\generic\zenodoDeposit\classes\ojs\DatabaseDepositStateStore;
use APP\plugins\generic\zenodoDeposit\classes\ojs\DepositJob;
use APP\plugins\generic\zenodoDeposit\classes\ojs\ZenodoPluginSettings;
use APP\plugins\generic\zenodoDeposit\classes\ojs\ZenodoDepositSchemaMigration;
use Illuminate\Database\Migrations\Migration;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use Throwable;

class ZenodoDepositPlugin extends GenericPlugin
{
    public const PLUGIN_NAME = 'zenododepositplugin';

    public function register($category, $path, $mainContextId = null): bool
    {
        if (!parent::register($category, $path, $mainContextId)) {
            return false;
        }

        if ($this->getEnabled()) {
            Hook::add('Publication::publish', [$this, 'onPublicationPublished']);
        }

        return true;
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

    /**
     * @param array<int, mixed> $args
     */
    public function onPublicationPublished(string $hookName, array $args): bool
    {
        $publicationId = null;

        try {
            $publication = $args[0] ?? null;

            if (!$publication instanceof Publication) {
                $publication = $args[1] ?? null;
            }

            $submission = $args[2] ?? null;

            if (!$publication instanceof Publication || !$submission instanceof Submission) {
                return false;
            }

            $publicationId = $this->positiveInteger($publication->getId());
            $submissionId = $this->positiveInteger($submission->getId());
            $contextId = $this->positiveInteger($submission->getData('contextId'));

            if ($publicationId === null || $submissionId === null || $contextId === null) {
                throw new ZenodoConfigException('Published OJS article identifiers are invalid.');
            }

            $config = ZenodoConfig::fromArray(ZenodoPluginSettings::forContext($contextId));
            $this->assertDepositConfig($config);
            $handler = new PublishHookHandler(
                new DatabaseDepositStateStore(),
                static function (int $queuedPublicationId, int $queuedContextId): mixed {
                    return DepositJob::dispatch($queuedPublicationId, $queuedContextId);
                },
                static function (Throwable $exception) use ($publicationId): void {
                    error_log(
                        '[zenodoDeposit] publication_id=' . $publicationId
                            . ' enqueue failed (' . get_class($exception) . ')'
                    );
                }
            );

            return $handler->handle($publicationId, $submissionId, $contextId);
        } catch (Throwable $exception) {
            error_log(
                '[zenodoDeposit] publication_id=' . ($publicationId ?? 'unknown')
                    . ' publish hook failed (' . get_class($exception) . ')'
            );

            return false;
        }
    }

    private function assertDepositConfig(ZenodoConfig $config): void
    {
        if ($config->mode !== ZenodoConfig::MODE_MOCK && $config->token === '') {
            throw new ZenodoConfigException(
                "Zenodo {$config->mode} mode requires zenodo_token to be configured."
            );
        }

        if ($config->mode === ZenodoConfig::MODE_SANDBOX) {
            $host = parse_url($config->baseUrl, PHP_URL_HOST);

            if (
                is_string($host)
                && in_array(rtrim(strtolower($host), '.'), ['zenodo.org', 'www.zenodo.org'], true)
            ) {
                throw new ZenodoConfigException('Zenodo sandbox mode must not point at production.');
            }
        }
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }
}
