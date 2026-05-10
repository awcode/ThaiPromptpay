<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip\Verify\Transport;

use Awcode\ThaiPromptpay\Slip\Verify\Exceptions\VerificationException;

/**
 * Default cURL-based transport for verifier providers. No external HTTP
 * dependencies — works on any PHP install with ext-curl.
 */
final class CurlTransport implements Transport
{
    public function postJson(string $url, array $headers, array $body, int $timeout = 15): array
    {
        $headers = array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $headers,
        );

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new VerificationException('Failed to JSON-encode request body: ' . json_last_error_msg());
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_USERAGENT => 'awcode/thaipromptpay (+https://github.com/awcode/ThaiPromptpay)',
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new VerificationException("HTTP request failed: {$error}");
        }

        $decoded = json_decode((string) $response, true);
        if (! is_array($decoded)) {
            throw new VerificationException(
                "Response was not valid JSON (HTTP {$status}): " . substr((string) $response, 0, 200),
                httpStatus: $status,
            );
        }

        return ['status' => $status, 'body' => $decoded];
    }
}
