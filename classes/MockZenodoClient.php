<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

final class MockZenodoClient implements ZenodoClientInterface
{
    public const SCENARIO_SUCCESS = 'success';

    public const SCENARIO_OUTAGE = 'outage';

    public const SCENARIO_VALIDATION_ERROR = 'validation_error';

    private const DEPOSITION_ID = 12345;

    private const DOI = '10.5072/zenodo.12345';

    private const MOCK_HOST = 'https://zenodo.mock.invalid';

    private const FIXED_TIMESTAMP = '2026-11-04T00:00:00Z';

    private ?array $deposition = null;

    public function __construct(private string $scenario = self::SCENARIO_SUCCESS)
    {
        $knownScenarios = [
            self::SCENARIO_SUCCESS,
            self::SCENARIO_OUTAGE,
            self::SCENARIO_VALIDATION_ERROR,
        ];

        if (!in_array($scenario, $knownScenarios, true)) {
            throw new ZenodoConfigException('Unknown Zenodo mock scenario.');
        }
    }

    public function createDeposition(array $metadata): array
    {
        $this->assertScenario();
        $this->deposition = $this->makeDraft($metadata);

        return $this->deposition;
    }

    public function uploadFile(int $depositionId, string $filename, string $contents): array
    {
        $this->assertScenario();
        $this->assertDepositionId($depositionId);
        $this->ensureDeposition();

        $file = [
            'key' => $filename,
            'size' => strlen($contents),
            'checksum' => 'md5:' . md5($contents),
            'mimetype' => 'application/octet-stream',
            'links' => [
                'self' => self::MOCK_HOST . '/api/deposit/depositions/' . self::DEPOSITION_ID
                    . '/files/' . rawurlencode($filename),
            ],
        ];
        return $file;
    }

    public function publish(int $depositionId): array
    {
        $this->assertScenario();
        $this->assertDepositionId($depositionId);
        $this->ensureDeposition();

        $this->deposition['state'] = 'done';
        $this->deposition['submitted'] = true;
        $this->deposition['doi'] = self::DOI;
        $this->deposition['doi_url'] = 'https://doi.org/' . self::DOI;
        $this->deposition['record_id'] = self::DEPOSITION_ID;

        return $this->deposition;
    }

    public function getDeposition(int $depositionId): array
    {
        $this->assertScenario();
        $this->assertDepositionId($depositionId);
        $this->ensureDeposition();

        return $this->deposition;
    }

    private function makeDraft(array $metadata): array
    {
        $metadata['prereserve_doi'] = [
            'doi' => self::DOI,
            'recid' => self::DEPOSITION_ID,
        ];

        $self = self::MOCK_HOST . '/api/deposit/depositions/' . self::DEPOSITION_ID;

        return [
            'id' => self::DEPOSITION_ID,
            'record_id' => self::DEPOSITION_ID,
            'state' => 'unsubmitted',
            'submitted' => false,
            'created' => self::FIXED_TIMESTAMP,
            'modified' => self::FIXED_TIMESTAMP,
            'owner' => 1,
            'title' => is_string($metadata['title'] ?? null) ? $metadata['title'] : 'Mock deposition',
            'metadata' => $metadata,
            'links' => [
                'bucket' => self::MOCK_HOST . '/api/files/' . self::DEPOSITION_ID,
                'discard' => $self . '/actions/discard',
                'edit' => $self,
                'files' => $self . '/files',
                'html' => self::MOCK_HOST . '/record/' . self::DEPOSITION_ID,
                'latest_draft' => $self,
                'latest_draft_html' => self::MOCK_HOST . '/record/' . self::DEPOSITION_ID,
                'publish' => $self . '/actions/publish',
                'self' => $self,
            ],
        ];
    }

    private function ensureDeposition(): void
    {
        if ($this->deposition === null) {
            $this->deposition = $this->makeDraft([]);
        }
    }

    private function assertDepositionId(int $depositionId): void
    {
        if ($depositionId !== self::DEPOSITION_ID) {
            $body = '{"message":"Deposition not found","status":404}';
            throw new ZenodoApiException('Zenodo deposition not found.', 404, $body);
        }
    }

    private function assertScenario(): void
    {
        if ($this->scenario === self::SCENARIO_OUTAGE) {
            throw new ZenodoApiException('Zenodo mock service unavailable.', 503, 'Service Unavailable');
        }

        if ($this->scenario === self::SCENARIO_VALIDATION_ERROR) {
            // The mock uses the requested 422 status; Zenodo docs show validation errors as 400.
            $body = '{"message":"Validation error","status":422,"errors":[{"field":"metadata.title",'
                . '"message":"Title is required."}]}';
            throw new ZenodoApiException('Zenodo mock validation error.', 422, $body);
        }
    }
}
