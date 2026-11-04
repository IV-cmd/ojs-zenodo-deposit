<?php

declare(strict_types=1);

namespace ZenodoDeposit\Tests;

use APP\plugins\generic\zenodoDeposit\classes\http\GuzzleTransport;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class GuzzleTransportTest extends TestCase
{
    public function testReturnsClientErrorsAndForwardsHeadersAndBody(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(422, ['Content-Type' => 'application/json'], '{"message":"invalid"}'),
        ]));
        $stack->push(Middleware::history($history));
        $transport = new GuzzleTransport(new Client(['handler' => $stack]));

        $response = $transport->send(
            'POST',
            'https://sandbox.zenodo.org/api/deposit/depositions',
            [
                'Authorization' => 'Bearer fake-token',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            '{"metadata":{"title":"Example"}}'
        );

        self::assertSame(422, $response->status);
        self::assertSame('{"message":"invalid"}', $response->body);
        self::assertCount(1, $history);
        self::assertSame(
            'Bearer fake-token',
            $history[0]['request']->getHeaderLine('Authorization')
        );
        self::assertSame(
            'application/json',
            $history[0]['request']->getHeaderLine('Content-Type')
        );
        self::assertSame('{"metadata":{"title":"Example"}}', (string) $history[0]['request']->getBody());
    }
}
