<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\DepositState;
use APP\plugins\generic\zenodoDeposit\classes\PublishHookHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZenodoDeposit\Tests\Support\InMemoryDepositStateStore;

class PublishHookHandlerTest extends TestCase
{
    public function testEnsuresPendingStateAndDispatchesOnlyTheJobIds(): void
    {
        $store = new InMemoryDepositStateStore();
        $dispatched = [];
        $logged = [];
        $handler = new PublishHookHandler(
            $store,
            static function (int $publicationId, int $contextId) use (&$dispatched): void {
                $dispatched[] = [$publicationId, $contextId];
            },
            static function (RuntimeException $exception) use (&$logged): void {
                $logged[] = $exception->getMessage();
            }
        );

        self::assertFalse($handler->handle(42, 7, 3));

        $state = $store->get(42);
        self::assertNotNull($state);
        self::assertSame(DepositState::STATUS_PENDING, $state->status);
        self::assertSame([[42, 3]], $dispatched);
        self::assertSame([], $logged);
    }

    public function testDispatchFailureIsLoggedAndSwallowed(): void
    {
        $store = new InMemoryDepositStateStore();
        $logged = [];
        $handler = new PublishHookHandler(
            $store,
            static function (int $publicationId, int $contextId): void {
                throw new RuntimeException('Queue unavailable.');
            },
            static function (\Throwable $exception) use (&$logged): void {
                $logged[] = $exception->getMessage();
            }
        );

        self::assertFalse($handler->handle(42, 7, 3));
        self::assertSame(['Queue unavailable.'], $logged);
        self::assertNotNull($store->get(42));
    }
}
