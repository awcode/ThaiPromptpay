<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay;

use Awcode\ThaiPromptpay\Slip\Parser as SlipParser;
use Awcode\ThaiPromptpay\Slip\Scanner as SlipScanner;
use Awcode\ThaiPromptpay\Slip\SlipQr;

/**
 * Top-level entry point for the package.
 *
 * Generation: each factory returns a {@see Builder}. Chain ->amount(...),
 * and for Bill Payment also ->ref1(...) / ->ref2(...), then call ->build()
 * to produce a {@see Payload}.
 *
 *   $payload = ThaiPromptpay::phone('0899999999')->amount(420)->build();
 *   echo $payload;            // EMVCo TLV string
 *   $svg = $payload->svg();   // SVG QR markup
 *
 *   $bill = ThaiPromptpay::billPayment('099400015804189')
 *       ->ref1('INV001')
 *       ->ref2('CUST123')
 *       ->amount(1500)
 *       ->build();
 *
 * Slip parsing: read an ITMX-standard slip-verify Mini-QR off a payload
 * string or an image of a slip. Returns parsed fields only — see {@see SlipQr}
 * for the caveat about what this proves vs. doesn't prove.
 *
 *   $slip = ThaiPromptpay::readSlip('/path/to/slip.jpg');
 *   $slip->sendingBank;     // "014"
 *   $slip->bankShortName;   // "SCB"
 *   $slip->transRef;        // bank-issued transaction reference
 */
class ThaiPromptpay
{
    public static function phone(string $phone): Builder
    {
        return Builder::forPhone($phone);
    }

    public static function nationalId(string $id): Builder
    {
        return Builder::forNationalId($id);
    }

    public static function eWallet(string $id): Builder
    {
        return Builder::forEWallet($id);
    }

    public static function billPayment(string $billerId): Builder
    {
        return Builder::forBillPayment($billerId);
    }

    /**
     * Parse a slip-verify Mini-QR payload string (no image scanning).
     */
    public static function parseSlip(string $payload): SlipQr
    {
        return SlipParser::parse($payload);
    }

    /**
     * Scan a slip image and parse the QR. Accepts a file path, raw image
     * bytes, or a base64 data URI. Requires khanamiryan/qrcode-detector-decoder.
     */
    public static function scanSlip(string $image): SlipQr
    {
        return SlipParser::parse(SlipScanner::decode($image));
    }

    /**
     * Auto-detect: if the input looks like a slip-verify TLV payload, parse
     * directly; otherwise treat it as image input and scan first.
     */
    public static function readSlip(string $input): SlipQr
    {
        $trimmed = trim($input);
        if (preg_match('/^00\d{2}/', $trimmed) === 1) {
            try {
                return SlipParser::parse($trimmed);
            } catch (\Throwable $e) {
                // Fall through to image scan if it merely looks TLV-shaped.
            }
        }

        return self::scanSlip($input);
    }
}
