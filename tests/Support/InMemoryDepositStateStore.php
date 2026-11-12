<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests\Support;

use APP\plugins\generic\zenodoDeposit\classes\DepositState;
use APP\plugins\generic\zenodoDeposit\classes\DepositStateStore;

final class InMemoryDepositStateStore implements DepositStateStore
{
    /**
     * @var array<int, DepositState>
     */
    private array $states = [];

    public function get(int $publicationId): ?DepositState
    {
        return $this->states[$publicationId] ?? null;
    }

    public function save(DepositState $state): void
    {
        $this->states[$state->publicationId] = $state;
    }

    public function ensurePending(DepositState $state): void
    {
        $this->states[$state->publicationId] ??= $state;
    }
}
