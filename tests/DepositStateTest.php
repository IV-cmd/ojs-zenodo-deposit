<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\DepositState;
use PHPUnit\Framework\TestCase;

class DepositStateTest extends TestCase
{
    public function testCreatesPendingStateAndCopiesCheckpoints(): void
    {
        $state = new DepositState(42, 7, 3);
        $creating = $state->withStatus(DepositState::STATUS_CREATING);
        $depositing = $creating->withArticleDeposition(12345);
        $deposited = $depositing->withDoi('10.5072/zenodo.12345')->withStatus(DepositState::STATUS_DEPOSITED);

        self::assertSame(DepositState::STATUS_PENDING, $state->status);
        self::assertSame(DepositState::STATUS_CREATING, $creating->status);
        self::assertSame(12345, $depositing->depositionId);
        self::assertSame('10.5072/zenodo.12345', $deposited->doi);
        self::assertSame(DepositState::STATUS_DEPOSITED, $deposited->status);
        self::assertNull($state->depositionId);
    }

    public function testCanClearLostDepositionAndRecordAttempts(): void
    {
        $state = (new DepositState(42, 7, 3))
            ->withArticleDeposition(12345)
            ->withDoi('10.5072/zenodo.12345')
            ->withAttempt(2, 'temporary error')
            ->withoutArticleDeposition();

        self::assertNull($state->depositionId);
        self::assertNull($state->doi);
        self::assertSame(DepositState::STATUS_PENDING, $state->status);
        self::assertSame(2, $state->attempts);
        self::assertSame('temporary error', $state->lastError);
    }

    public function testWithRetryResetsToPendingButKeepsDepositionIds(): void
    {
        $failed = new DepositState(
            42,
            7,
            3,
            DepositState::STATUS_FAILED,
            depositionId: 12345,
            attempts: 5,
            lastError: 'Zenodo mock validation error.'
        );

        $retry = $failed->withRetry();

        self::assertSame(DepositState::STATUS_PENDING, $retry->status);
        self::assertSame(12345, $retry->depositionId);
        self::assertSame(5, $retry->attempts);
        self::assertNull($retry->lastError);
        self::assertSame(DepositState::STATUS_FAILED, $failed->status);
    }

    public function testRejectsUnknownState(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $createState = static fn (): DepositState => new DepositState(42, 7, 3, 'unknown');
        $createState();
    }
}
