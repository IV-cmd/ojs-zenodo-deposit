<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\MockZenodoClient;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoApiException;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoConfigException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MockZenodoClientTest extends TestCase
{
    public function testSuccessFlowReturnsDraftFileAndPublishedResource(): void
    {
        $client = new MockZenodoClient();
        $draft = $client->createDeposition(['title' => 'Example']);

        self::assertSame(12345, $draft['id']);
        self::assertSame('unsubmitted', $draft['state']);
        self::assertFalse($draft['submitted']);
        self::assertSame('Example', $draft['metadata']['title']);
        self::assertSame('10.5072/zenodo.12345', $draft['metadata']['prereserve_doi']['doi']);
        self::assertSame(12345, $draft['metadata']['prereserve_doi']['recid']);
        self::assertStringContainsString('.invalid', $draft['links']['bucket']);
        self::assertSame(
            'https://zenodo.mock.invalid/api/deposit/depositions/12345/actions/edit',
            $draft['links']['edit']
        );
        self::assertSame('https://zenodo.mock.invalid/deposit/12345', $draft['links']['html']);
        self::assertSame('https://zenodo.mock.invalid/deposit/12345', $draft['links']['latest_draft_html']);
        self::assertArrayHasKey('self', $draft['links']);
        self::assertArrayHasKey('publish', $draft['links']);
        self::assertArrayHasKey('html', $draft['links']);
        self::assertArrayHasKey('discard', $draft['links']);
        self::assertArrayHasKey('edit', $draft['links']);
        self::assertArrayHasKey('files', $draft['links']);
        self::assertArrayHasKey('latest_draft', $draft['links']);
        self::assertArrayHasKey('latest_draft_html', $draft['links']);

        $file = $client->uploadFile(12345, 'paper.pdf', 'file contents');
        self::assertSame('paper.pdf', $file['key']);
        self::assertSame(strlen('file contents'), $file['size']);
        self::assertSame('md5:' . md5('file contents'), $file['checksum']);
        self::assertSame('application/octet-stream', $file['mimetype']);
        self::assertArrayHasKey('self', $file['links']);

        $published = $client->publish(12345);
        self::assertSame('done', $published['state']);
        self::assertTrue($published['submitted']);
        self::assertSame('10.5072/zenodo.12345', $published['doi']);
        self::assertSame('https://doi.org/10.5072/zenodo.12345', $published['doi_url']);
        self::assertSame(12345, $published['record_id']);
        self::assertSame($published, $client->getDeposition(12345));
    }

    public function testInstancesReturnDeterministicSuccessfulResources(): void
    {
        $first = new MockZenodoClient();
        $second = new MockZenodoClient();

        self::assertSame(
            $first->createDeposition(['title' => 'Stable']),
            $second->createDeposition(['title' => 'Stable'])
        );
        self::assertSame(
            $first->publish(12345),
            $second->publish(12345)
        );
    }

    public function testOutageScenarioReturns503ForEveryMethod(): void
    {
        $client = new MockZenodoClient(MockZenodoClient::SCENARIO_OUTAGE);
        $operations = [
            fn (): array => $client->createDeposition([]),
            fn (): array => $client->uploadFile(12345, 'file.txt', 'contents'),
            fn (): array => $client->publish(12345),
            fn (): array => $client->getDeposition(12345),
        ];

        foreach ($operations as $operation) {
            $exception = $this->captureApiException($operation);
            self::assertSame(503, $exception->getStatusCode());
            self::assertSame('Service Unavailable', $exception->getResponseBody());
        }
    }

    public function testValidationScenarioReturns422AndStructuredError(): void
    {
        $client = new MockZenodoClient(MockZenodoClient::SCENARIO_VALIDATION_ERROR);
        $operations = [
            fn (): array => $client->createDeposition([]),
            fn (): array => $client->uploadFile(12345, 'file.txt', 'contents'),
            fn (): array => $client->publish(12345),
            fn (): array => $client->getDeposition(12345),
        ];

        foreach ($operations as $operation) {
            $exception = $this->captureApiException($operation);
            $body = json_decode($exception->getResponseBody(), true);

            self::assertSame(422, $exception->getStatusCode());
            self::assertIsArray($body);
            self::assertSame('Validation error', $body['message']);
            self::assertSame(422, $body['status']);
            self::assertSame('metadata.title', $body['errors'][0]['field']);
        }
    }

    public function testUnknownDepositionIdReturns404(): void
    {
        $exception = $this->captureApiException(
            fn (): array => (new MockZenodoClient())->getDeposition(999)
        );

        self::assertSame(404, $exception->getStatusCode());
        self::assertSame('{"message":"Deposition not found","status":404}', $exception->getResponseBody());
    }

    public function testUnknownScenarioThrowsConfigurationException(): void
    {
        $this->expectException(ZenodoConfigException::class);
        new MockZenodoClient('unknown');
    }

    /**
     * @param callable(): array $operation
     */
    private function captureApiException(callable $operation): ZenodoApiException
    {
        try {
            $operation();
        } catch (ZenodoApiException $exception) {
            return $exception;
        }

        self::fail('Expected a ZenodoApiException.');
    }
}
