<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip\Verify;

use Awcode\ThaiPromptpay\Slip\Parser;
use Awcode\ThaiPromptpay\Slip\Scanner;
use Awcode\ThaiPromptpay\Slip\Verify\Transport\CurlTransport;
use Awcode\ThaiPromptpay\Slip\Verify\Transport\Transport;

abstract class AbstractVerifier implements Verifier
{
    public function __construct(protected ?Transport $transport = null)
    {
        $this->transport ??= new CurlTransport();
    }

    /**
     * Coerce a generic input (TLV payload string, image path, image bytes,
     * or data URI) into a slip-verify Mini-QR payload string.
     */
    protected function resolvePayload(string $input): string
    {
        $trimmed = trim($input);

        if (preg_match('/^00\d{2}/', $trimmed) === 1) {
            try {
                Parser::parse($trimmed);

                return $trimmed;
            } catch (\Throwable) {
                // Fall through to image scan if the TLV-shaped input doesn't actually parse.
            }
        }

        return Scanner::decode($input);
    }

    protected function parseDate(string $value): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new \RuntimeException("Unable to parse date '{$value}': " . $e->getMessage(), 0, $e);
        }
    }
}
