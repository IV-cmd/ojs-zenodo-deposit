<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests\Support;

use APP\plugins\generic\zenodoDeposit\classes\ZenodoApiException;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoClientInterface;
use Throwable;

final class RecordingZenodoClient implements ZenodoClientInterface
{
    /**
     * @var list<array{method: string, arguments: array<mixed>}>
     */
    public array $calls = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $depositions = [];

    /**
     * @var array<string, list<Throwable>>
     */
    private array $queuedExceptions = [];

    private int $nextId = 12345;

    public function queueException(string $method, Throwable $exception): void
    {
        $this->queuedExceptions[$method][] = $exception;
    }

    /**
     * @param list<string> $fileNames
     * @return array<string, mixed>
     */
    public function seedDeposition(array $metadata, array $fileNames = [], bool $published = false): array
    {
        $resource = $this->makeDeposition($this->nextId++, $metadata);

        foreach ($fileNames as $fileName) {
            $resource['files'][] = ['key' => $fileName];
        }

        if ($published) {
            $resource['state'] = 'done';
            $resource['submitted'] = true;
            $resource['doi'] = '10.5072/zenodo.' . $resource['id'];
        }

        $this->depositions[$resource['id']] = $resource;

        return $resource;
    }

    public function createDeposition(array $metadata): array
    {
        $this->calls[] = ['method' => 'createDeposition', 'arguments' => [$metadata]];
        $this->throwQueuedException('createDeposition');
        $resource = $this->makeDeposition($this->nextId++, $metadata);
        $this->depositions[$resource['id']] = $resource;

        return $resource;
    }

    public function uploadFile(int $depositionId, string $filename, string $contents): array
    {
        $this->calls[] = ['method' => 'uploadFile', 'arguments' => [$depositionId, $filename, $contents]];
        $this->throwQueuedException('uploadFile');
        $resource = $this->getStoredDeposition($depositionId);
        $file = [
            'key' => $filename,
            'size' => strlen($contents),
            'checksum' => 'md5:' . md5($contents),
            'mimetype' => 'application/octet-stream',
        ];
        $resource['files'][] = $file;
        $this->depositions[$depositionId] = $resource;

        return $file;
    }

    public function publish(int $depositionId): array
    {
        $this->calls[] = ['method' => 'publish', 'arguments' => [$depositionId]];
        $this->throwQueuedException('publish');
        $resource = $this->getStoredDeposition($depositionId);
        $resource['state'] = 'done';
        $resource['submitted'] = true;
        $resource['doi'] = '10.5072/zenodo.' . $depositionId;
        $resource['doi_url'] = 'https://doi.org/' . $resource['doi'];
        $resource['record_id'] = $depositionId;
        $this->depositions[$depositionId] = $resource;

        return $resource;
    }

    public function getDeposition(int $depositionId): array
    {
        $this->calls[] = ['method' => 'getDeposition', 'arguments' => [$depositionId]];
        $this->throwQueuedException('getDeposition');

        return $this->getStoredDeposition($depositionId);
    }

    public function listDepositions(array $query = []): array
    {
        $this->calls[] = ['method' => 'listDepositions', 'arguments' => [$query]];
        $this->throwQueuedException('listDepositions');
        $depositions = array_values($this->depositions);

        if (($query['status'] ?? null) === 'draft') {
            $depositions = array_values(array_filter(
                $depositions,
                static fn (array $deposition): bool => ($deposition['submitted'] ?? false) !== true
            ));
        }

        return $depositions;
    }

    /**
     * @return array<string, mixed>
     */
    private function makeDeposition(int $id, array $metadata): array
    {
        return [
            'id' => $id,
            'record_id' => $id,
            'state' => 'unsubmitted',
            'submitted' => false,
            'metadata' => $metadata,
            'files' => [],
            'links' => [
                'bucket' => 'https://zenodo.mock.invalid/api/files/' . $id,
                'self' => 'https://zenodo.mock.invalid/api/deposit/depositions/' . $id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getStoredDeposition(int $depositionId): array
    {
        if (!isset($this->depositions[$depositionId])) {
            throw new ZenodoApiException(
                'Zenodo deposition not found.',
                404,
                '{"message":"Deposition not found","status":404}'
            );
        }

        return $this->depositions[$depositionId];
    }

    private function throwQueuedException(string $method): void
    {
        if (!empty($this->queuedExceptions[$method])) {
            throw array_shift($this->queuedExceptions[$method]);
        }
    }
}
