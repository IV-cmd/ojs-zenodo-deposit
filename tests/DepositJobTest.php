<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\RetryPolicy;
use APP\plugins\generic\zenodoDeposit\classes\ojs\DepositJob;
use PHPUnit\Framework\TestCase;

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
}
