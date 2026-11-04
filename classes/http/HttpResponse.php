<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes\http;

final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body
    ) {
    }
}
