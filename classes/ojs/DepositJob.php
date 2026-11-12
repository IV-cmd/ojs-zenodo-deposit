<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes\ojs;

use APP\plugins\generic\zenodoDeposit\classes\DepositService;
use APP\plugins\generic\zenodoDeposit\classes\DepositState;
use APP\plugins\generic\zenodoDeposit\classes\PermanentDepositException;
use APP\plugins\generic\zenodoDeposit\classes\RetryPolicy;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoClientFactory;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfig;
use PKP\jobs\BaseJob;
use Throwable;

final class DepositJob extends BaseJob
{
    /** @var int */
    public $tries = RetryPolicy::MAX_ATTEMPTS;

    public int $maxExceptions = RetryPolicy::MAX_ATTEMPTS;

    public int $timeout = 120;

    public function __construct(
        private int $publicationId,
        private int $contextId
    ) {
        parent::__construct();
        unset($this->backoff);
    }

    public function backoff(): string
    {
        return implode(',', (new RetryPolicy())->backoffSchedule());
    }

    public function handle(): void
    {
        $store = new DatabaseDepositStateStore();
        $token = '';
        $attempt = 1;

        try {
            $settings = ZenodoPluginSettings::forContext($this->contextId);
            $config = ZenodoConfig::fromArray($settings);
            $token = $config->token;
            $state = $store->get($this->publicationId);

            if ($state === null) {
                throw new PermanentDepositException('Deposit state is missing.');
            }

            $attempt = $state->attempts + 1;
            $state = $state->withAttempt($attempt, null);
            $store->save($state);
            $this->logAttempt($attempt, $state->depositionId, 'started');

            $loaded = (new ArticleLoader())->load($this->publicationId, $this->contextId, $settings);
            $client = ZenodoClientFactory::make($config);
            $service = new DepositService(
                $client,
                $store,
                function (string $message) use ($attempt, $store): void {
                    $this->logAttempt(
                        $attempt,
                        $store->get($this->publicationId)?->depositionId,
                        $message
                    );
                }
            );
            $service->run($this->publicationId, $loaded['mappedPayload'], $loaded['fileReader']);
        } catch (Throwable $exception) {
            $transient = (new RetryPolicy())->isTransient($exception);
            $this->recordFailure($store, $exception, $token, $attempt, !$transient);

            if ($transient) {
                throw $exception;
            }

            $this->fail($exception);
        }
    }

    public function failed(Throwable $exception): void
    {
        $store = new DatabaseDepositStateStore();
        $token = '';

        try {
            $settings = ZenodoPluginSettings::forContext($this->contextId);
            $token = ZenodoConfig::fromArray($settings)->token;
        } catch (Throwable) {
        }

        $state = $store->get($this->publicationId);
        $attempt = $state === null ? 1 : $state->attempts;
        $this->recordFailure($store, $exception, $token, $attempt, true);
    }

    private function recordFailure(
        DatabaseDepositStateStore $store,
        Throwable $exception,
        string $token,
        int $attempt,
        bool $permanent
    ): void {
        $message = $this->safeError($exception, $token);
        $state = $store->get($this->publicationId);

        if ($state !== null) {
            $state = $state->withAttempt(max($attempt, $state->attempts), $message);

            if ($permanent) {
                $state = $state->withStatus(DepositState::STATUS_FAILED);
            }

            $store->save($state);
        }

        $this->logAttempt($attempt, $state?->depositionId, 'failed: ' . $message);
    }

    private function safeError(Throwable $exception, string $token): string
    {
        $message = $exception->getMessage();

        if ($token !== '') {
            $message = str_replace($token, '[REDACTED]', $message);
        }

        if (preg_match('/^.{0,2000}/us', $message, $matches) === 1) {
            return $matches[0];
        }

        return substr($message, 0, 2000);
    }

    private function logAttempt(int $attempt, ?int $depositionId, string $message): void
    {
        error_log(
            '[zenodoDeposit] publication_id=' . $this->publicationId
                . ' context_id=' . $this->contextId
                . ' attempt=' . $attempt
                . ' deposition_id=' . ($depositionId ?? 'unknown')
                . ' ' . $message
        );
    }
}
