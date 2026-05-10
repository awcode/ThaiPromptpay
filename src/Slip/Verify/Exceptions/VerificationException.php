<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip\Verify\Exceptions;

use RuntimeException;

class VerificationException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $response  Raw provider response body, when available.
     */
    public function __construct(
        string $message,
        public readonly string $provider = '',
        public readonly ?int $httpStatus = null,
        public readonly array $response = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
