<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

use Closure;
use Throwable;

final class PublishHookHandler
{
    public function __construct(
        private DepositStateStore $stateStore,
        private Closure $dispatcher,
        private Closure $logger
    ) {
    }

    public function handle(int $publicationId, int $submissionId, int $contextId): bool
    {
        try {
            $this->stateStore->ensurePending(new DepositState($publicationId, $submissionId, $contextId));
            ($this->dispatcher)($publicationId, $contextId);
        } catch (Throwable $exception) {
            try {
                ($this->logger)($exception);
            } catch (Throwable) {
            }
        }

        return false;
    }
}
