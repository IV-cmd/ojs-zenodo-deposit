<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes\http;

use GuzzleHttp\ClientInterface;

final class GuzzleTransport implements HttpTransport
{
    public function __construct(private ClientInterface $client)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body = null): HttpResponse
    {
        $response = $this->client->request($method, $url, [
            'headers' => $headers,
            'body' => $body,
            'http_errors' => false,
        ]);

        return new HttpResponse($response->getStatusCode(), (string) $response->getBody());
    }
}
