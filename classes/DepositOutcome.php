<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

final readonly class DepositOutcome
{
    public function __construct(
        public int $publicationId,
        public int $depositionId,
        public string $doi,
        public ?int $datasetDepositionId = null,
        public ?string $datasetDoi = null,
        public bool $alreadyDeposited = false
    ) {
    }
}
