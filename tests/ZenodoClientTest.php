<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\ZenodoApiException;
use APP\plugins\generic\zenodoDeposit\classes\ZenodoClient;
use APP\plugins\generic\zenodoDeposit\classes\http\HttpResponse;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZenodoDeposit\Tests\Support\FakeTransport;

class ZenodoClientTest extends TestCase
{
    private const BASE_URL = 'https://sandbox.zenodo.org';

    private const TOKEN = 'test-secret-token';

    public function testCreateDepositionUsesTrimmedUrlAndJsonBodies(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(201, '{"id":1}'),
            new HttpResponse(201, '{"id":2}'),
        ]);
        $client = new ZenodoClient(self::BASE_URL . '///', self::TOKEN, $transport);

        $client->createDeposition([]);
        $client->createDeposition(['title' => 'Example']);

        self::assertCount(2, $transport->calls);
        self::assertSame('POST', $transport->calls[0]['method']);
        self::assertSame(self::BASE_URL . '/api/deposit/depositions', $transport->calls[0]['url']);
        self::assertSame('{}', $transport->calls[0]['body']);
        self::assertSame('{"metadata":{"title":"Example"}}', $transport->calls[1]['body']);

        foreach ($transport->calls as $call) {
            $this->assertCommonHeaders($call);
            self::assertSame('application/json', $call['headers']['Content-Type']);
        }
    }

    public function testGetAndPublishUseExpectedMethodsAndUrls(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"id":18}'),
            new HttpResponse(202, '{"id":18,"state":"done"}'),
        ]);
        $client = new ZenodoClient(self::BASE_URL, self::TOKEN, $transport);

        $client->getDeposition(18);
        $client->publish(18);

        self::assertSame('GET', $transport->calls[0]['method']);
        self::assertSame(self::BASE_URL . '/api/deposit/depositions/18', $transport->calls[0]['url']);
        self::assertSame('POST', $transport->calls[1]['method']);
        self::assertSame(
            self::BASE_URL . '/api/deposit/depositions/18/actions/publish',
            $transport->calls[1]['url']
        );
        self::assertNull($transport->calls[1]['body']);

        foreach ($transport->calls as $call) {
            $this->assertCommonHeaders($call);
        }
    }

    public function testUploadFetchesBucketThenPutsEncodedFilenameAndRawContents(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"links":{"bucket":"' . self::BASE_URL . '/api/files/18"}}'),
            new HttpResponse(201, '{"key":"paper #1.pdf"}'),
        ]);
        $client = new ZenodoClient(self::BASE_URL, self::TOKEN, $transport);

        $result = $client->uploadFile(18, 'paper #1.pdf', "raw\nfile bytes");

        self::assertSame(['key' => 'paper #1.pdf'], $result);
        self::assertCount(2, $transport->calls);
        self::assertSame('GET', $transport->calls[0]['method']);
        self::assertSame(self::BASE_URL . '/api/deposit/depositions/18', $transport->calls[0]['url']);
        self::assertSame('PUT', $transport->calls[1]['method']);
        self::assertSame(
            self::BASE_URL . '/api/files/18/paper%20%231.pdf',
            $transport->calls[1]['url']
        );
        self::assertSame("raw\nfile bytes", $transport->calls[1]['body']);
        self::assertSame('application/octet-stream', $transport->calls[1]['headers']['Content-Type']);

        foreach ($transport->calls as $call) {
            $this->assertCommonHeaders($call);
        }
    }

    public function testUploadRejectsForeignBucketWithoutSendingPut(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"links":{"bucket":"https://uploads.example.invalid/bucket"}}'),
        ]);
        $client = new ZenodoClient(self::BASE_URL, self::TOKEN, $transport);

        $exception = $this->captureApiException(
            fn (): array => $client->uploadFile(18, 'paper.pdf', 'contents')
        );

        self::assertSame(0, $exception->getStatusCode());
        self::assertStringContainsString('different origin', $exception->getMessage());
        self::assertCount(1, $transport->calls);
        self::assertSame('GET', $transport->calls[0]['method']);
    }

    public function testUploadRejectsBucketOnDifferentPortWithoutSendingPut(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"links":{"bucket":"https://sandbox.zenodo.org:8443/api/files/18"}}'),
        ]);
        $client = new ZenodoClient(self::BASE_URL, self::TOKEN, $transport);

        $exception = $this->captureApiException(
            fn (): array => $client->uploadFile(18, 'paper.pdf', 'contents')
        );

        self::assertSame(0, $exception->getStatusCode());
        self::assertStringContainsString('different origin', $exception->getMessage());
        self::assertCount(1, $transport->calls);
        self::assertSame('GET', $transport->calls[0]['method']);
    }

    public function testUploadRejectsMissingBucket(): void
    {
        $transport = new FakeTransport([
            new HttpResponse(200, '{"links":[]}'),
        ]);
        $client = new ZenodoClient(self::BASE_URL, self::TOKEN, $transport);

        $exception = $this->captureApiException(
            fn (): array => $client->uploadFile(18, 'paper.pdf', 'contents')
        );

        self::assertSame(0, $exception->getStatusCode());
        self::assertStringContainsString('missing a string links.bucket', $exception->getMessage());
        self::assertCount(1, $transport->calls);
    }

    public function testNonSuccessResponsesPreserveStatusAndRedactedBody(): void
    {
        foreach ([422, 503] as $status) {
            $body = '{"message":"failed","status":' . $status . '}';
            $transport = new FakeTransport([new HttpResponse($status, $body)]);
            $client = new ZenodoClient(self::BASE_URL, self::TOKEN, $transport);

            $exception = $this->captureApiException(
                fn (): array => $client->createDeposition([])
            );

            self::assertSame($status, $exception->getStatusCode());
            self::assertSame($body, $exception->getResponseBody());
            self::assertSame(
                "Zenodo API POST /api/deposit/depositions failed with HTTP $status",
                $exception->getMessage()
            );
        }
    }

    public function testTransportFailureHasStatusZeroAndNoPreviousException(): void
    {
        $transport = new FakeTransport([new RuntimeException('transport failed')]);
        $client = new ZenodoClient(self::BASE_URL, self::TOKEN, $transport);

        $exception = $this->captureApiException(
            fn (): array => $client->getDeposition(18)
        );

        self::assertSame(0, $exception->getStatusCode());
        self::assertSame('', $exception->getResponseBody());
        self::assertNull($exception->getPrevious());
        self::assertStringContainsString(
            'Zenodo API GET /api/deposit/depositions/18 transport error: transport failed',
            $exception->getMessage()
        );
    }

    public function testInvalidSuccessfulResponseIsRejectedWithActualStatus(): void
    {
        foreach ([new HttpResponse(200, 'not-json'), new HttpResponse(202, '')] as $response) {
            $client = new ZenodoClient(
                self::BASE_URL,
                self::TOKEN,
                new FakeTransport([$response])
            );

            $exception = $this->captureApiException(
                fn (): array => $client->getDeposition(18)
            );

            self::assertSame($response->status, $exception->getStatusCode());
            self::assertSame($response->body, $exception->getResponseBody());
            self::assertStringContainsString('empty or invalid JSON', $exception->getMessage());
        }
    }

    public function testTokenIsRedactedFromHttpAndTransportErrors(): void
    {
        $httpClient = new ZenodoClient(
            self::BASE_URL,
            self::TOKEN,
            new FakeTransport([new HttpResponse(422, '{"message":"' . self::TOKEN . '"}')])
        );
        $httpException = $this->captureApiException(
            fn (): array => $httpClient->getDeposition(18)
        );

        self::assertStringNotContainsString(self::TOKEN, $httpException->getMessage());
        self::assertStringNotContainsString(self::TOKEN, $httpException->getResponseBody());
        self::assertStringNotContainsString(self::TOKEN, (string) $httpException);
        self::assertStringContainsString('[REDACTED]', $httpException->getResponseBody());

        $transportClient = new ZenodoClient(
            self::BASE_URL,
            self::TOKEN,
            new FakeTransport([new RuntimeException('failed with ' . self::TOKEN)])
        );
        $transportException = $this->captureApiException(
            fn (): array => $transportClient->getDeposition(18)
        );

        self::assertStringNotContainsString(self::TOKEN, $transportException->getMessage());
        self::assertStringNotContainsString(self::TOKEN, $transportException->getResponseBody());
        self::assertStringNotContainsString(self::TOKEN, (string) $transportException);
        self::assertStringContainsString('[REDACTED]', $transportException->getMessage());
    }

    /**
     * @param array{method: string, url: string, headers: array<string, mixed>, body: ?string} $call
     */
    private function assertCommonHeaders(array $call): void
    {
        self::assertSame('Bearer ' . self::TOKEN, $call['headers']['Authorization']);
        self::assertSame('application/json', $call['headers']['Accept']);
    }

    /**
     * @param callable(): mixed $operation
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
