<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\DepositState;
use APP\plugins\generic\zenodoDeposit\classes\DepositStateStore;
use APP\plugins\generic\zenodoDeposit\classes\RetryPolicy;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoApiException;
use APP\plugins\generic\zenodoDeposit\classes\ojs\DepositJob;
use PHPUnit\Framework\TestCase;
use ZenodoDeposit\Tests\Support\InMemoryDepositStateStore;

class DepositJobTest extends TestCase
{
    public function testConfiguresQueueRetriesAndTimeout(): void
    {
        $job = new DepositJob(42, 3);

        self::assertSame(5, $job->tries);
        self::assertSame(5, $job->maxExceptions);
        self::assertSame(120, $job->timeout);
        self::assertSame('30,60,120,240', $job->backoff());
        self::assertFalse((new \ReflectionProperty($job, 'backoff'))->isInitialized($job));
        self::assertSame(RetryPolicy::MAX_ATTEMPTS, $job->tries);
    }

    public function testFailedMarksStateFailedAndPreservesDepositionId(): void
    {
        $store = new InMemoryDepositStateStore();
        $store->ensurePending(new DepositState(42, 7, 3));
        $store->save(
            $store->get(42)
                ->withArticleDeposition(12345)
                ->withAttempt(5, null)
        );

        $this->jobWithStore(42, 3, $store)
            ->failed(new ZenodoApiException('giving up', 503, ''));

        $state = $store->get(42);
        self::assertNotNull($state);
        self::assertSame(DepositState::STATUS_FAILED, $state->status);
        self::assertSame(12345, $state->depositionId);
        self::assertSame(5, $state->attempts);
        self::assertSame('giving up', $state->lastError);
    }

    public function testFailedWithMissingStateDoesNotThrow(): void
    {
        $store = new InMemoryDepositStateStore();
        $this->jobWithStore(42, 3, $store)
            ->failed(new ZenodoApiException('gone', 500, ''));

        self::assertNull($store->get(42));
    }

    public function testSafeErrorRedactsTokenAndTruncates(): void
    {
        $redacted = DepositJob::safeError(
            new \RuntimeException('Zenodo rejected token abc123'),
            'abc123'
        );
        self::assertSame('Zenodo rejected token [REDACTED]', $redacted);

        $long = DepositJob::safeError(
            new \RuntimeException(str_repeat('x', 3000)),
            ''
        );
        self::assertSame(2000, mb_strlen($long));
    }

    private function jobWithStore(
        int $publicationId,
        int $contextId,
        DepositStateStore $store
    ): DepositJob {
        return new class ($publicationId, $contextId, $store) extends DepositJob {
            public function __construct(
                int $publicationId,
                int $contextId,
                private DepositStateStore $stateStore
            ) {
                parent::__construct($publicationId, $contextId);
            }

            protected function newStore(): DepositStateStore
            {
                return $this->stateStore;
            }
        };
    }
}
