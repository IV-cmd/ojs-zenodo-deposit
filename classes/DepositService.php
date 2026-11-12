<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

use Closure;
use Throwable;

final class DepositService
{
    private ?Closure $logger;

    public function __construct(
        private ZenodoClientInterface $client,
        private DepositStateStore $stateStore,
        ?Closure $logger = null
    ) {
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $mappedPayload
     * @param callable(array{submissionFileId: int, name: string, path: string}): string $fileReader
     */
    public function run(int $publicationId, array $mappedPayload, callable $fileReader): DepositOutcome
    {
        $state = $this->stateStore->get($publicationId);

        if ($state === null) {
            throw new PermanentDepositException('Deposit state is missing; publish the article again.');
        }

        if ($state->status === DepositState::STATUS_DEPOSITED) {
            return $this->outcomeFromState($state, true);
        }

        $article = $this->section($mappedPayload, 'article');
        $articleMarker = $this->relatedIdentifier($article['metadata'], 'isAlternateIdentifier');
        [$state, $articleId, $articleResource] = $this->resolveDeposition(
            $state,
            $state->depositionId,
            $article['metadata'],
            $articleMarker,
            false
        );
        $articleDoi = $this->completeDeposition(
            $articleId,
            $articleResource,
            $article['files'],
            $fileReader
        );
        $state = $state->withDoi($articleDoi);
        $this->stateStore->save($state);

        if (isset($mappedPayload['dataset'])) {
            if (!is_array($mappedPayload['dataset'])) {
                throw new PermanentDepositException('Dataset mapping is invalid.');
            }

            $dataset = $this->section($mappedPayload, 'dataset');
            $datasetMarker = $this->relatedIdentifier($dataset['metadata'], 'isSupplementTo');
            [$state, $datasetId, $datasetResource] = $this->resolveDeposition(
                $state,
                $state->datasetDepositionId,
                $dataset['metadata'],
                $datasetMarker,
                true
            );
            $datasetDoi = $this->completeDeposition(
                $datasetId,
                $datasetResource,
                $dataset['files'],
                $fileReader
            );
            $state = $state->withDatasetDoi($datasetDoi);
            $this->stateStore->save($state);
        }

        $state = $state->withStatus(DepositState::STATUS_DEPOSITED);
        $this->stateStore->save($state);

        return $this->outcomeFromState($state);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *     metadata: array<string, mixed>,
     *     files: array<mixed>
     * }
     */
    private function section(array $payload, string $sectionName): array
    {
        $section = $payload[$sectionName] ?? null;

        if (
            !is_array($section)
            || !is_array($section['metadata'] ?? null)
            || !is_array($section['files'] ?? null)
        ) {
            throw new PermanentDepositException('Deposit mapping is missing a valid ' . $sectionName . ' section.');
        }

        return [
            'metadata' => $section['metadata'],
            'files' => $section['files'],
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array{DepositState, int, array<string, mixed>}
     */
    private function resolveDeposition(
        DepositState $state,
        ?int $depositionId,
        array $metadata,
        ?string $marker,
        bool $dataset
    ): array {
        if ($depositionId !== null) {
            try {
                return [$state, $depositionId, $this->client->getDeposition($depositionId)];
            } catch (ZenodoApiException $exception) {
                if ($exception->getStatusCode() !== 404) {
                    throw $exception;
                }

                $state = $dataset ? $state->withoutDatasetDeposition() : $state->withoutArticleDeposition();
                $this->stateStore->save($state);
            }
        }

        if ($state->status === DepositState::STATUS_CREATING) {
            if ($marker === null) {
                throw new PermanentDepositException(
                    'A deposition may have been created, but no recovery marker is available; manual check needed.'
                );
            }

            $matches = $this->findMatchingDeposition(
                $this->client->listDepositions([
                    'status' => 'draft',
                    'sort' => 'mostrecent',
                    'size' => 100,
                ]),
                $marker,
                $dataset ? 'isSupplementTo' : 'isAlternateIdentifier'
            );

            if ($matches !== null) {
                $depositionId = $this->depositionId($matches);
                $state = $dataset
                    ? $state->withDatasetDeposition($depositionId)
                    : $state->withArticleDeposition($depositionId);
                $this->stateStore->save($state);

                return [$state, $depositionId, $this->client->getDeposition($depositionId)];
            }
        }

        $state = $state->withStatus(DepositState::STATUS_CREATING);
        $this->stateStore->save($state);
        $resource = $this->client->createDeposition($metadata);
        $depositionId = $this->depositionId($resource);
        $state = $dataset
            ? $state->withDatasetDeposition($depositionId)
            : $state->withArticleDeposition($depositionId);
        $this->stateStore->save($state);
        $this->logCreated($depositionId, $dataset);

        return [$state, $depositionId, $this->client->getDeposition($depositionId)];
    }

    /**
     * @param array<string, mixed> $deposition
     * @param array<mixed> $files
     * @param callable(array{submissionFileId: int, name: string, path: string}): string $fileReader
     */
    private function completeDeposition(
        int $depositionId,
        array $deposition,
        array $files,
        callable $fileReader
    ): string {
        $doi = $deposition['doi'] ?? null;

        if (
            (($deposition['submitted'] ?? false) === true || ($deposition['state'] ?? null) === 'done')
            && is_string($doi)
            && $doi !== ''
        ) {
            return $doi;
        }

        $existingFileNames = $this->existingFileNames($deposition);

        foreach ($files as $file) {
            if (!is_array($file)) {
                throw new PermanentDepositException('Deposit mapping contains an invalid file reference.');
            }

            $submissionFileId = $file['submissionFileId'] ?? null;
            $name = $file['name'] ?? null;
            $path = $file['path'] ?? null;

            if (!is_int($submissionFileId) || !is_string($name) || $name === '' || !is_string($path)) {
                throw new PermanentDepositException('Deposit mapping contains an invalid file reference.');
            }

            if (isset($existingFileNames[$name])) {
                continue;
            }

            $fileReference = [
                'submissionFileId' => $submissionFileId,
                'name' => $name,
                'path' => $path,
            ];
            $contents = $fileReader($fileReference);
            $this->client->uploadFile($depositionId, $name, $contents);
            $existingFileNames[$name] = true;
        }

        $published = $this->client->publish($depositionId);
        $doi = $published['doi'] ?? null;

        if (is_string($doi) && $doi !== '') {
            return $doi;
        }

        $deposition = $this->client->getDeposition($depositionId);
        $doi = $deposition['doi'] ?? null;

        if (!is_string($doi) || $doi === '') {
            throw new ZenodoApiException('Zenodo published the deposition without returning a DOI.', 0, '');
        }

        return $doi;
    }

    /**
     * @param array<string, mixed> $deposition
     * @return array<string, true>
     */
    private function existingFileNames(array $deposition): array
    {
        $existing = [];
        $files = $deposition['files'] ?? [];

        if (!is_array($files)) {
            return $existing;
        }

        foreach ($files as $file) {
            if (is_array($file) && is_string($file['key'] ?? null)) {
                $existing[$file['key']] = true;
            }
        }

        return $existing;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function relatedIdentifier(array $metadata, string $relation): ?string
    {
        $relatedIdentifiers = $metadata['related_identifiers'] ?? null;

        if (!is_array($relatedIdentifiers)) {
            return null;
        }

        foreach ($relatedIdentifiers as $identifier) {
            if (
                is_array($identifier)
                && ($identifier['relation'] ?? null) === $relation
                && is_string($identifier['identifier'] ?? null)
                && $identifier['identifier'] !== ''
            ) {
                return $identifier['identifier'];
            }
        }

        return null;
    }

    /**
     * @param array<int|string, mixed> $depositions
     * @return array<string, mixed>|null
     */
    private function findMatchingDeposition(array $depositions, string $marker, string $relation): ?array
    {
        foreach ($depositions as $deposition) {
            if (!is_array($deposition)) {
                continue;
            }

            $metadata = $deposition['metadata'] ?? null;

            if (!is_array($metadata)) {
                continue;
            }

            if ($this->relatedIdentifier($metadata, $relation) === $marker) {
                return $deposition;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $resource
     */
    private function depositionId(array $resource): int
    {
        $id = $resource['id'] ?? null;

        if (is_int($id) && $id > 0) {
            return $id;
        }

        if (is_string($id) && ctype_digit($id) && (int) $id > 0) {
            return (int) $id;
        }

        throw new PermanentDepositException('Zenodo returned a deposition without a valid id.');
    }

    private function outcomeFromState(DepositState $state, bool $alreadyDeposited = false): DepositOutcome
    {
        if ($state->depositionId === null || $state->doi === null) {
            throw new PermanentDepositException('Completed deposit state is missing the article DOI or deposition id.');
        }

        return new DepositOutcome(
            $state->publicationId,
            $state->depositionId,
            $state->doi,
            $state->datasetDepositionId,
            $state->datasetDoi,
            $alreadyDeposited
        );
    }

    private function logCreated(int $depositionId, bool $dataset): void
    {
        if ($this->logger !== null) {
            ($this->logger)(
                'created ' . ($dataset ? 'dataset' : 'article') . ' deposition id=' . $depositionId
            );
        }
    }
}
