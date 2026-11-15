<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

use InvalidArgumentException;

final readonly class DepositState
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CREATING = 'creating';

    public const STATUS_DEPOSITING = 'depositing';

    public const STATUS_DEPOSITED = 'deposited';

    public const STATUS_FAILED = 'failed';

    /**
     * @param string $status
     */
    public function __construct(
        public int $publicationId,
        public int $submissionId,
        public int $contextId,
        public string $status = self::STATUS_PENDING,
        public ?int $depositionId = null,
        public ?int $datasetDepositionId = null,
        public ?string $doi = null,
        public ?string $datasetDoi = null,
        public int $attempts = 0,
        public ?string $lastError = null
    ) {
        if (
            !in_array(
                $status,
                [
                self::STATUS_PENDING,
                self::STATUS_CREATING,
                self::STATUS_DEPOSITING,
                self::STATUS_DEPOSITED,
                self::STATUS_FAILED,
                ],
                true
            )
        ) {
            throw new InvalidArgumentException('Unknown Zenodo deposit state.');
        }
    }

    public function withStatus(string $status): self
    {
        return $this->copy(status: $status);
    }

    public function withArticleDeposition(int $depositionId): self
    {
        return $this->copy(
            status: self::STATUS_DEPOSITING,
            depositionId: $depositionId,
            clearDoi: true
        );
    }

    public function withoutArticleDeposition(): self
    {
        return $this->copy(
            status: self::STATUS_PENDING,
            clearDepositionId: true,
            clearDoi: true
        );
    }

    public function withDatasetDeposition(int $depositionId): self
    {
        return $this->copy(
            status: self::STATUS_DEPOSITING,
            datasetDepositionId: $depositionId,
            clearDatasetDoi: true
        );
    }

    public function withoutDatasetDeposition(): self
    {
        return $this->copy(
            status: self::STATUS_PENDING,
            clearDatasetDepositionId: true,
            clearDatasetDoi: true
        );
    }

    public function withDoi(string $doi): self
    {
        return $this->copy(doi: $doi);
    }

    public function withDatasetDoi(string $doi): self
    {
        return $this->copy(datasetDoi: $doi);
    }

    /**
     * Manual retry (M4): back to pending while keeping stored deposition IDs —
     * the service reuses them, so a retry can never create a duplicate.
     */
    public function withRetry(): self
    {
        return $this->copy(status: self::STATUS_PENDING, lastError: null, replaceLastError: true);
    }

    public function withAttempt(int $attempts, ?string $lastError): self
    {
        return $this->copy(
            attempts: $attempts,
            lastError: $lastError,
            replaceLastError: true
        );
    }

    private function copy(
        ?string $status = null,
        ?int $depositionId = null,
        ?int $datasetDepositionId = null,
        ?string $doi = null,
        ?string $datasetDoi = null,
        ?int $attempts = null,
        ?string $lastError = null,
        bool $clearDepositionId = false,
        bool $clearDatasetDepositionId = false,
        bool $clearDoi = false,
        bool $clearDatasetDoi = false,
        bool $replaceLastError = false
    ): self {
        return new self(
            $this->publicationId,
            $this->submissionId,
            $this->contextId,
            $status ?? $this->status,
            $clearDepositionId ? null : ($depositionId ?? $this->depositionId),
            $clearDatasetDepositionId ? null : ($datasetDepositionId ?? $this->datasetDepositionId),
            $clearDoi ? null : ($doi ?? $this->doi),
            $clearDatasetDoi ? null : ($datasetDoi ?? $this->datasetDoi),
            $attempts ?? $this->attempts,
            $replaceLastError ? $lastError : $this->lastError
        );
    }
}
