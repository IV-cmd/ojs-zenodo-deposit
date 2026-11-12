<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\DepositMappingException;
use APP\plugins\generic\zenodoDeposit\classes\RetryPolicy;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoApiException;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfigException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class RetryPolicyTest extends TestCase
{
    public function testClassifiesTransientZenodoFailures(): void
    {
        $policy = new RetryPolicy();

        foreach ([0, 429, 500, 502, 503] as $statusCode) {
            self::assertTrue(
                $policy->isTransient(new ZenodoApiException('temporary', $statusCode, ''))
            );
        }
    }

    public function testClassifiesPermanentFailures(): void
    {
        $policy = new RetryPolicy();

        foreach ([400, 401, 403, 404, 422] as $statusCode) {
            self::assertFalse(
                $policy->isTransient(new ZenodoApiException('permanent', $statusCode, ''))
            );
        }

        self::assertFalse($policy->isTransient(new DepositMappingException('invalid metadata')));
        self::assertFalse($policy->isTransient(new ZenodoConfigException('invalid settings')));
        self::assertFalse($policy->isTransient(new RuntimeException('unexpected failure')));
    }

    public function testProvidesFiveAttemptsAndExponentialBackoff(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(5, RetryPolicy::MAX_ATTEMPTS);
        self::assertSame([30, 60, 120, 240], $policy->backoffSchedule());
        self::assertSame(480, $policy->backoffSeconds(5));
        self::assertSame(3600, $policy->backoffSeconds(20));
    }
}
