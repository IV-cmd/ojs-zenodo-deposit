<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

interface DepositStateStore
{
    public function get(int $publicationId): ?DepositState;

    public function save(DepositState $state): void;

    public function ensurePending(DepositState $state): void;
}
