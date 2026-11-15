<?php

/**
 * Generic plugin skeleton for Zenodo Deposit.
 *
 * @license MIT
 */

namespace APP\plugins\generic\zenodoDeposit;

use APP\plugins\generic\zenodoDeposit\classes\DepositSettings;
use APP\plugins\generic\zenodoDeposit\classes\DepositState;
use APP\plugins\generic\zenodoDeposit\classes\PublishHookHandler;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfig;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfigException;
use APP\plugins\generic\zenodoDeposit\classes\ojs\DatabaseDepositStateStore;
use APP\plugins\generic\zenodoDeposit\classes\ojs\DepositJob;
use APP\plugins\generic\zenodoDeposit\classes\ojs\ZenodoDepositSettingsForm;
use APP\plugins\generic\zenodoDeposit\classes\ojs\ZenodoPluginSettings;
use APP\plugins\generic\zenodoDeposit\classes\ojs\ZenodoDepositSchemaMigration;
use Illuminate\Database\Migrations\Migration;
use APP\publication\Publication;
use APP\submission\Submission;
use PKP\core\JSONMessage;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
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
            Hook::add('Schema::get::publication', [$this, 'extendPublicationSchema']);
            Hook::add('Templates::Article::Details', [$this, 'displayDepositBadge']);
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
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $router = $request->getRouter();

        return array_merge(
            [
                new LinkAction(
                    'settings',
                    new AjaxModal(
                        $router->url(
                            $request,
                            null,
                            null,
                            'manage',
                            null,
                            [
                                'verb' => 'settings',
                                'plugin' => $this->getName(),
                                'category' => 'generic',
                            ]
                        ),
                        __('manager.plugins.settings'),
                        'modal_settings'
                    ),
                    __('manager.plugins.settings'),
                    null
                ),
            ],
            parent::getActions($request, $actionArgs)
        );
    }

    /**
     * @copydoc Plugin::manage()
     *
     * Routed by the core plugin grid; callers are already journal managers.
     * verb=settings renders/saves the form; verb=retry re-queues a failed
     * deposit through the same idempotent path as the publish hook.
     */
    public function manage($args, $request)
    {
        $context = $request->getContext();

        if ($context === null) {
            return new JSONMessage(false);
        }

        switch ($request->getUserVar('verb')) {
            case 'settings':
                $form = new ZenodoDepositSettingsForm($this, (int) $context->getId());

                if ($request->getUserVar('save')) {
                    $form->readInputData();

                    if ($form->validate()) {
                        $form->execute();

                        return new JSONMessage(true);
                    }
                } else {
                    $form->initData();
                }

                return new JSONMessage(true, $form->fetch($request));

            case 'retry':
                return $this->manageRetry($request, (int) $context->getId());
        }

        return parent::manage($args, $request);
    }

    /**
     * Reset a failed deposit to pending and re-dispatch DepositJob. The M3
     * service path is unchanged: stored deposition IDs are reused, so a
     * manual retry can never create a second deposition.
     */
    private function manageRetry($request, int $contextId): JSONMessage
    {
        $publicationId = (int) $request->getUserVar('publicationId');
        $store = new DatabaseDepositStateStore();
        $state = $store->get($publicationId);

        if (
            $publicationId <= 0
            || $state === null
            || $state->contextId !== $contextId
            || $state->status !== DepositState::STATUS_FAILED
        ) {
            return new JSONMessage(
                true,
                __('plugins.generic.zenodoDeposit.deposit.retryNotAllowed')
            );
        }

        $store->save($state->withRetry());
        DepositJob::dispatch($publicationId, $contextId);

        return new JSONMessage(
            true,
            __('plugins.generic.zenodoDeposit.deposit.retryQueued')
        );
    }

    /**
     * Declare the deposit projection on the publication schema (M4).
     *
     * DatabaseDepositStateStore mirrors every checkpoint into a
     * publication_settings row of this name, so the prop rides along on the
     * publication object and in API responses (SchemaDAO only loads settings
     * that match declared schema properties).
     *
     * @param array<int, mixed> $args
     */
    public function extendPublicationSchema(string $hookName, array $args): bool
    {
        $schema = $args[0] ?? null;

        if (!is_object($schema) || !isset($schema->properties)) {
            return false;
        }

        if (!isset($schema->properties->{'zenodoDeposit'})) {
            $schema->properties->{'zenodoDeposit'} = json_decode(
                '{"type":"object","apiSummary":true,"validation":["nullable"]}'
            );
        }

        return false;
    }

    /**
     * Article-page deposit panel (M4): status, Zenodo DOI link, last error and
     * a manager-only retry action. Runs on Templates::Article::Details.
     *
     * @param array<int, mixed> $args
     */
    public function displayDepositBadge(string $hookName, array $args): bool
    {
        $output = & $args[0];
        $templateMgr = $args[1] ?? null;
        $request = \APP\core\Application::get()->getRequest();

        $article = $templateMgr?->getTemplateVars('article');
        $publication = $templateMgr?->getTemplateVars('publication')
            ?? $article?->getCurrentPublication();

        if ($publication === null) {
            return false;
        }

        $state = (new DatabaseDepositStateStore())->get((int) $publication->getId());

        if ($state === null) {
            return false;
        }

        $settings = DepositSettings::fromArray(
            ZenodoPluginSettings::forContext($state->contextId)
        );

        $user = $request->getUser();
        $context = $request->getContext();
        $isManager = $user !== null
            && $context !== null
            && $user->hasRole(
                [\PKP\security\Role::ROLE_ID_MANAGER, \PKP\security\Role::ROLE_ID_SITE_ADMIN],
                (int) $context->getId()
            );

        $templateMgr->assign([
            'zenodoDepositStatus' => $state->status,
            'zenodoDepositDoi' => $state->doi,
            'zenodoDepositRecordUrl' => $state->doi === null ? null
                : ($settings->sandbox ? 'https://sandbox.zenodo.org' : 'https://zenodo.org')
                    . '/records/' . str_replace('zenodo.', '', explode('/', $state->doi)[1] ?? $state->doi),
            'zenodoDepositError' => $isManager ? $state->lastError : null,
            'zenodoDepositRetryUrl' => $isManager && $state->status === DepositState::STATUS_FAILED
                ? $request->getRouter()->url(
                    $request,
                    null,
                    null,
                    'manage',
                    null,
                    [
                        'verb' => 'retry',
                        'plugin' => $this->getName(),
                        'category' => 'generic',
                        'publicationId' => $state->publicationId,
                    ]
                )
                : null,
        ]);

        $output .= $templateMgr->fetch($this->getTemplateResource('depositBadge.tpl'));

        return false;
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

            $storedSettings = ZenodoPluginSettings::forContext($contextId);

            // Journals opt in deliberately: deposits default to off (M4).
            if (!DepositSettings::fromArray($storedSettings)->enabled) {
                return false;
            }

            $config = ZenodoConfig::fromArray($storedSettings);
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
