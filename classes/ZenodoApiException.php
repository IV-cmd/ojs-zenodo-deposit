<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes;

use RuntimeException;

final class ZenodoApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $statusCode,
        private string $responseBody
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
