<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Tests\Support;

use Awcode\ThaiPromptpay\Slip\Verify\Transport\Transport;

/**
 * In-memory Transport double for tests. Captures the request and returns a
 * preconfigured response. No network involved.
 */
final class FakeTransport implements Transport
{
    /** @var array<int, array{url: string, headers: array<string, string>, body: array<string, mixed>}> */
    public array $calls = [];

    /** @var int */
    public int $status = 200;

    /** @var array<string, mixed> */
    public array $response = ['success' => true, 'data' => []];

    public function postJson(string $url, array $headers, array $body, int $timeout = 15): array
    {
        $this->calls[] = ['url' => $url, 'headers' => $headers, 'body' => $body];

        return ['status' => $this->status, 'body' => $this->response];
    }
}
