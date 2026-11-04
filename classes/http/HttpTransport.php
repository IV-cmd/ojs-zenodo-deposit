<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes\http;

interface HttpTransport
{
    public function send(string $method, string $url, array $headers, ?string $body = null): HttpResponse;
}
