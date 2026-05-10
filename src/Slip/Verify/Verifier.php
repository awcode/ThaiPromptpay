<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip\Verify;

interface Verifier
{
    /**
     * Verify a slip with the underlying provider.
     *
     * @param  string  $input  Slip-verify Mini-QR payload string OR an image
     *                         (file path, raw bytes, or data URI). Implementations
     *                         must accept both.
     */
    public function verify(string $input): SlipVerification;
}
