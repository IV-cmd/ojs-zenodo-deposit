<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\DepositService;
use APP\plugins\generic\zenodoDeposit\classes\DepositState;
use APP\plugins\generic\zenodoDeposit\classes\PermanentDepositException;
use APP\plugins\generic\zenodoDeposit\classes\RetryPolicy;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoApiException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZenodoDeposit\Tests\Support\InMemoryDepositStateStore;
use ZenodoDeposit\Tests\Support\RecordingZenodoClient;

class DepositServiceTest extends TestCase
{
    public function testHappyPathPersistsArticleDepositionAndDoi(): void
    {
        $store = $this->pendingStore();
        $client = new RecordingZenodoClient();
        $createdMessages = [];
        $service = new DepositService(
            $client,
            $store,
            static function (string $message) use (&$createdMessages): void {
                $createdMessages[] = $message;
            }
        );

        $outcome = $service->run(42, $this->payload(), $this->fileReader());
        $state = $store->get(42);

        self::assertNotNull($state);
        self::assertSame(DepositState::STATUS_DEPOSITED, $state->status);
        self::assertSame(12345, $state->depositionId);
        self::assertSame('10.5072/zenodo.12345', $state->doi);
        self::assertSame(12345, $outcome->depositionId);
        self::assertSame('10.5072/zenodo.12345', $outcome->doi);
        self::assertSame(1, $this->countCalls($client, 'createDeposition'));
        self::assertSame(1, $this->countCalls($client, 'publish'));
        self::assertSame(['created article deposition id=12345'], $createdMessages);
    }

    public function testTransientUploadFailureResumesWithoutCreatingAgain(): void
    {
        $store = $this->pendingStore();
        $client = new RecordingZenodoClient();
        $client->queueException('uploadFile', new ZenodoApiException('unavailable', 503, ''));
        $service = new DepositService($client, $store);

        try {
            $service->run(42, $this->payload($this->oneFile()), $this->fileReader());
            self::fail('Expected the upload error.');
        } catch (ZenodoApiException $exception) {
            self::assertSame(503, $exception->getStatusCode());
        }

        $stateAfterFailure = $store->get(42);
        self::assertNotNull($stateAfterFailure);
        self::assertSame(12345, $stateAfterFailure->depositionId);
        self::assertSame(DepositState::STATUS_DEPOSITING, $stateAfterFailure->status);

        $outcome = $service->run(42, $this->payload($this->oneFile()), $this->fileReader());

        self::assertSame('10.5072/zenodo.12345', $outcome->doi);
        self::assertSame(1, $this->countCalls($client, 'createDeposition'));
        self::assertSame(2, $this->countCalls($client, 'uploadFile'));
        self::assertSame(1, $this->countCalls($client, 'publish'));
    }

    public function testPermanentCreateFailureIsNotRetryable(): void
    {
        $store = $this->pendingStore();
        $client = new RecordingZenodoClient();
        $client->queueException('createDeposition', new ZenodoApiException('invalid', 422, '{}'));
        $service = new DepositService($client, $store);

        try {
            $service->run(42, $this->payload(), $this->fileReader());
            self::fail('Expected the validation error.');
        } catch (ZenodoApiException $exception) {
            self::assertFalse((new RetryPolicy())->isTransient($exception));
        }

        self::assertSame(DepositState::STATUS_CREATING, $store->get(42)?->status);
        self::assertSame(1, $this->countCalls($client, 'createDeposition'));
    }

    public function testCrashAfterIdCheckpointResumesWithoutCreatingAgain(): void
    {
        $store = $this->pendingStore();
        $client = new RecordingZenodoClient();
        $service = new DepositService($client, $store);
        $throwingReader = static function (array $file): string {
            throw new RuntimeException('Simulated worker crash after the id checkpoint.');
        };

        try {
            $service->run(42, $this->payload($this->oneFile()), $throwingReader);
            self::fail('Expected the simulated crash.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Simulated worker crash', $exception->getMessage());
        }

        $stateAfterCrash = $store->get(42);
        self::assertNotNull($stateAfterCrash);
        self::assertSame(12345, $stateAfterCrash->depositionId);
        $service->run(42, $this->payload($this->oneFile()), $this->fileReader());

        self::assertSame(1, $this->countCalls($client, 'createDeposition'));
        self::assertSame(DepositState::STATUS_DEPOSITED, $store->get(42)->status);
    }

    public function testCrashBeforeIdCheckpointRecoversDraftByArticleMarker(): void
    {
        $store = $this->pendingStore();
        $store->failNextDepositCheckpoint = true;
        $client = new RecordingZenodoClient();
        $service = new DepositService($client, $store);

        try {
            $service->run(42, $this->payload(), $this->fileReader());
            self::fail('Expected the simulated state checkpoint failure.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Simulated state checkpoint failure', $exception->getMessage());
        }

        $stateAfterCrash = $store->get(42);
        self::assertNotNull($stateAfterCrash);
        self::assertSame(DepositState::STATUS_CREATING, $stateAfterCrash->status);
        self::assertNull($stateAfterCrash->depositionId);
        $service->run(42, $this->payload(), $this->fileReader());

        self::assertSame(1, $this->countCalls($client, 'createDeposition'));
        self::assertSame(1, $this->countCalls($client, 'listDepositions'));
        $stateAfterRecovery = $store->get(42);
        self::assertNotNull($stateAfterRecovery);
        self::assertSame(12345, $stateAfterRecovery->depositionId);
        self::assertSame(DepositState::STATUS_DEPOSITED, $stateAfterRecovery->status);
    }

    public function testAlreadyDepositedStateMakesNoClientCalls(): void
    {
        $store = new InMemoryDepositStateStore();
        $store->save(
            (new DepositState(42, 7, 3))
                ->withArticleDeposition(12345)
                ->withDoi('10.5072/zenodo.12345')
                ->withStatus(DepositState::STATUS_DEPOSITED)
        );
        $client = new RecordingZenodoClient();
        $service = new DepositService($client, $store);

        $outcome = $service->run(42, $this->payload(), $this->fileReader());

        self::assertTrue($outcome->alreadyDeposited);
        self::assertSame([], $client->calls);
    }

    public function testExistingRemoteFilenameIsNotUploadedAgain(): void
    {
        $store = new InMemoryDepositStateStore();
        $client = new RecordingZenodoClient();
        $resource = $client->seedDeposition($this->articleMetadata(), ['paper.pdf']);
        $store->save((new DepositState(42, 7, 3))->withArticleDeposition($resource['id']));
        $service = new DepositService($client, $store);

        $service->run(42, $this->payload($this->oneFile()), $this->fileReader());

        self::assertSame(0, $this->countCalls($client, 'uploadFile'));
        self::assertSame(1, $this->countCalls($client, 'publish'));
    }

    public function testDatasetCreatesAndPersistsASecondLinkedDeposition(): void
    {
        $store = $this->pendingStore();
        $client = new RecordingZenodoClient();
        $service = new DepositService($client, $store);
        $datasetMetadata = [
            'title' => 'Example dataset',
            'related_identifiers' => [
                [
                    'identifier' => 'https://journal.example.test/article/view/42',
                    'relation' => 'isSupplementTo',
                ],
            ],
        ];
        $payload = $this->payload([], [
            'metadata' => $datasetMetadata,
            'files' => [],
        ]);

        $outcome = $service->run(42, $payload, $this->fileReader());
        $state = $store->get(42);

        self::assertNotNull($state);
        self::assertSame(2, $this->countCalls($client, 'createDeposition'));
        self::assertSame(12345, $state->depositionId);
        self::assertSame(12346, $state->datasetDepositionId);
        self::assertSame('10.5072/zenodo.12345', $state->doi);
        self::assertSame('10.5072/zenodo.12346', $state->datasetDoi);
        self::assertSame($state->datasetDepositionId, $outcome->datasetDepositionId);
        self::assertSame(
            $datasetMetadata,
            $client->calls[3]['arguments'][0] ?? null
        );
    }

    public function testCreatingStateWithoutRecoveryMarkerFailsForManualReview(): void
    {
        $store = new InMemoryDepositStateStore();
        $store->save((new DepositState(42, 7, 3))->withStatus(DepositState::STATUS_CREATING));
        $client = new RecordingZenodoClient();
        $service = new DepositService($client, $store);
        $payload = $this->payload();
        $payload['article']['metadata'] = ['title' => 'No recovery marker'];

        $this->expectException(PermanentDepositException::class);
        $service->run(42, $payload, $this->fileReader());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $files = [], ?array $dataset = null): array
    {
        $payload = [
            'article' => [
                'metadata' => $this->articleMetadata(),
                'files' => $files,
            ],
            'warnings' => [],
        ];

        if ($dataset !== null) {
            $payload['dataset'] = $dataset;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function articleMetadata(): array
    {
        return [
            'title' => 'Example article',
            'related_identifiers' => [
                [
                    'identifier' => 'https://journal.example.test/article/view/42',
                    'relation' => 'isAlternateIdentifier',
                ],
            ],
        ];
    }

    /**
     * @return list<array{submissionFileId: int, name: string, path: string}>
     */
    private function oneFile(): array
    {
        return [
            [
                'submissionFileId' => 17,
                'name' => 'paper.pdf',
                'path' => '/private/paper.pdf',
            ],
        ];
    }

    /**
     * @return callable(array{submissionFileId: int, name: string, path: string}): string
     */
    private function fileReader(): callable
    {
        return static fn (array $file): string => 'contents of ' . $file['name'];
    }

    private function pendingStore(): InMemoryDepositStateStore
    {
        $store = new InMemoryDepositStateStore();
        $store->ensurePending(new DepositState(42, 7, 3));

        return $store;
    }

    private function countCalls(RecordingZenodoClient $client, string $method): int
    {
        return count(array_filter(
            $client->calls,
            static fn (array $call): bool => $call['method'] === $method
        ));
    }
}
