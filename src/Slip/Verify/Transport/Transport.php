<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip\Verify\Transport;

use Awcode\ThaiPromptpay\Slip\Verify\Exceptions\VerificationException;

interface Transport
{
    /**
     * Send a JSON POST request.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @return array{status: int, body: array<string, mixed>}
     *
     * @throws VerificationException on transport-level error (network, timeout, malformed JSON).
     */
    public function postJson(string $url, array $headers, array $body, int $timeout = 15): array;
}
