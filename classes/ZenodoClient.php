<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

use APP\plugins\generic\zenodoDeposit\classes\http\HttpTransport;
use JsonException;
use Throwable;

/**
 * Fails fast with typed errors and makes one request per operation without retries or sleeps.
 * A future queued job owns backoff.
 */
final class ZenodoClient implements ZenodoClientInterface
{
    private const API_PATH = '/api/deposit/depositions';

    private string $baseUrl;

    private string $token;

    public function __construct(
        string $baseUrl,
        #[\SensitiveParameter] string $token,
        private HttpTransport $transport
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->token = $token;
    }

    public function createDeposition(array $metadata): array
    {
        $payload = $metadata === [] ? new \stdClass() : ['metadata' => $metadata];

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw $this->exception(
                'Zenodo API POST ' . self::API_PATH . ' request encoding error: ' . $exception->getMessage(),
                0,
                ''
            );
        }

        return $this->request(
            'POST',
            self::API_PATH,
            $this->baseUrl . self::API_PATH,
            $body,
            'application/json'
        );
    }

    public function uploadFile(int $depositionId, string $filename, string $contents): array
    {
        $deposition = $this->getDeposition($depositionId);
        $bucket = $deposition['links']['bucket'] ?? null;

        if (!is_string($bucket) || $bucket === '') {
            throw $this->exception(
                'Zenodo API GET ' . self::API_PATH . '/' . $depositionId
                    . ' response is missing a string links.bucket',
                0,
                ''
            );
        }

        if (!$this->hasSameOrigin($bucket)) {
            throw $this->exception(
                'Zenodo API refused an upload because links.bucket has a different scheme or host',
                0,
                ''
            );
        }

        $url = rtrim($bucket, '/') . '/' . rawurlencode($filename);

        // TODO: Verify upload PUT success status and required Content-Type against live sandbox.
        return $this->request(
            'PUT',
            $this->pathFromUrl($url),
            $url,
            $contents,
            'application/octet-stream'
        );
    }

    public function publish(int $depositionId): array
    {
        $path = self::API_PATH . '/' . $depositionId . '/actions/publish';

        // TODO: Verify whether doi and doi_url are present in the publish 202 response on live sandbox.
        return $this->request('POST', $path, $this->baseUrl . $path);
    }

    public function getDeposition(int $depositionId): array
    {
        $path = self::API_PATH . '/' . $depositionId;

        return $this->request('GET', $path, $this->baseUrl . $path);
    }

    private function request(
        string $method,
        string $path,
        string $url,
        ?string $body = null,
        ?string $contentType = null
    ): array {
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];

        if ($contentType !== null) {
            $headers['Content-Type'] = $contentType;
        }

        try {
            $response = $this->transport->send($method, $url, $headers, $body);
        } catch (Throwable $exception) {
            throw $this->exception(
                "Zenodo API $method $path transport error: " . $exception->getMessage(),
                0,
                ''
            );
        }

        if ($response->status < 200 || $response->status >= 300) {
            throw $this->exception(
                "Zenodo API $method $path failed with HTTP {$response->status}",
                $response->status,
                $response->body
            );
        }

        if ($response->body === '') {
            throw $this->exception(
                "Zenodo API $method $path returned an empty or invalid JSON response with HTTP {$response->status}",
                $response->status,
                $response->body
            );
        }

        $decoded = json_decode($response->body, true);

        if (!is_array($decoded)) {
            throw $this->exception(
                "Zenodo API $method $path returned an empty or invalid JSON response with HTTP {$response->status}",
                $response->status,
                $response->body
            );
        }

        return $decoded;
    }

    private function hasSameOrigin(string $url): bool
    {
        $base = parse_url($this->baseUrl);
        $candidate = parse_url($url);

        if (!is_array($base) || !is_array($candidate)) {
            return false;
        }

        return strtolower($base['scheme'] ?? '') === strtolower($candidate['scheme'] ?? '')
            && strtolower($base['host'] ?? '') === strtolower($candidate['host'] ?? '')
            && ($base['host'] ?? '') !== '';
    }

    private function pathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private function exception(string $message, int $statusCode, string $responseBody): ZenodoApiException
    {
        return new ZenodoApiException(
            $this->redact($message),
            $statusCode,
            $this->redact($responseBody)
        );
    }

    private function redact(string $value): string
    {
        if ($this->token === '') {
            return $value;
        }

        return str_replace($this->token, '[REDACTED]', $value);
    }
}
