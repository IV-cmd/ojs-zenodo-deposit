<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests\Support;

use APP\plugins\generic\zenodoDeposit\classes\DepositState;
use APP\plugins\generic\zenodoDeposit\classes\DepositStateStore;

final class InMemoryDepositStateStore implements DepositStateStore
{
    public bool $failNextDepositCheckpoint = false;

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
        if ($this->failNextDepositCheckpoint && $state->depositionId !== null) {
            $this->failNextDepositCheckpoint = false;
            throw new \RuntimeException('Simulated state checkpoint failure.');
        }

        $this->states[$state->publicationId] = $state;
    }

    public function ensurePending(DepositState $state): void
    {
        $this->states[$state->publicationId] ??= $state;
    }
}
