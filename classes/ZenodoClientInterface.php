<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

interface ZenodoClientInterface
{
    public function createDeposition(array $metadata): array;

    public function uploadFile(int $depositionId, string $filename, string $contents): array;

    public function publish(int $depositionId): array;

    public function getDeposition(int $depositionId): array;

    public function listDepositions(array $query = []): array;
}
