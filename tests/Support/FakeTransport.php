<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests\Support;

use APP\plugins\generic\zenodoDeposit\classes\http\HttpResponse;
use APP\plugins\generic\zenodoDeposit\classes\http\HttpTransport;
use LogicException;
use Throwable;

final class FakeTransport implements HttpTransport
{
    /**
     * @var list<array{method: string, url: string, headers: array<string, mixed>, body: ?string}>
     */
    public array $calls = [];

    /**
     * @var list<HttpResponse|Throwable>
     */
    private array $responses;

    /**
     * @param list<HttpResponse|Throwable> $responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    public function queue(HttpResponse|Throwable $response): void
    {
        $this->responses[] = $response;
    }

    public function send(string $method, string $url, array $headers, ?string $body = null): HttpResponse
    {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        $response = array_shift($this->responses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        if (!$response instanceof HttpResponse) {
            throw new LogicException('Fake transport has no queued response.');
        }

        return $response;
    }
}
