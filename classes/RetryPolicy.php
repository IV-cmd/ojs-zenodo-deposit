<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

use Throwable;

final class RetryPolicy
{
    public const MAX_ATTEMPTS = 5;

    private const INITIAL_BACKOFF_SECONDS = 30;

    private const MAX_BACKOFF_SECONDS = 3600;

    public function isTransient(Throwable $exception): bool
    {
        if (!$exception instanceof ZenodoApiException) {
            return false;
        }

        $statusCode = $exception->getStatusCode();

        return $statusCode === 0 || $statusCode === 429 || $statusCode >= 500;
    }

    public function backoffSeconds(int $attempt): int
    {
        $backoff = self::INITIAL_BACKOFF_SECONDS;

        for ($currentAttempt = 1; $currentAttempt < $attempt; $currentAttempt++) {
            $backoff = min($backoff * 2, self::MAX_BACKOFF_SECONDS);
        }

        return $backoff;
    }

    /**
     * @return list<int>
     */
    public function backoffSchedule(): array
    {
        $schedule = [];

        for ($attempt = 1; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $schedule[] = $this->backoffSeconds($attempt);
        }

        return $schedule;
    }
}
