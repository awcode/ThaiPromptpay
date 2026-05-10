<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay;

use Awcode\ThaiPromptpay\Slip\Parser as SlipParser;
use Awcode\ThaiPromptpay\Slip\Scanner as SlipScanner;
use Awcode\ThaiPromptpay\Slip\SlipQr;
use Awcode\ThaiPromptpay\Slip\Verify\EasySlipVerifier;
use Awcode\ThaiPromptpay\Slip\Verify\SlipOkVerifier;
use Awcode\ThaiPromptpay\Slip\Verify\SlipVerification;
use Awcode\ThaiPromptpay\Slip\Verify\Transport\Transport;
use Awcode\ThaiPromptpay\Slip\Verify\Verifier;

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
    public function __construct(private readonly ?Verifier $verifier = null)
    {
    }

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

    /**
     * Build a SlipOK verifier (slipok.com).
     *
     *   ThaiPromptpay::slipOk($apiKey, $branchId)->verify($payloadOrImage);
     */
    public static function slipOk(string $apiKey, string $branchId, ?Transport $transport = null): SlipOkVerifier
    {
        return new SlipOkVerifier($apiKey, $branchId, $transport);
    }

    /**
     * Build an EasySlip v2 verifier (easyslip.com).
     *
     *   ThaiPromptpay::easySlip($apiKey)->verify($payloadOrImage);
     */
    public static function easySlip(string $apiKey, ?Transport $transport = null): EasySlipVerifier
    {
        return new EasySlipVerifier($apiKey, $transport);
    }

    /**
     * Verify a slip via the configured default Verifier.
     *
     * Requires a Verifier to have been injected (via Laravel config + the
     * service provider, or by constructing `new ThaiPromptpay($verifier)`
     * yourself). Otherwise use the explicit ::slipOk() / ::easySlip() factories.
     */
    public function verify(string $input): SlipVerification
    {
        if ($this->verifier === null) {
            throw new \LogicException(
                'No default Verifier is configured. Either set thaipromptpay.verifier.default'
                . ' in config and supply credentials, or use ThaiPromptpay::slipOk(...)->verify($input)'
                . ' / ThaiPromptpay::easySlip(...)->verify($input) directly.'
            );
        }

        return $this->verifier->verify($input);
    }
}
