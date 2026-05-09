<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay;

/**
 * Top-level entry point for the package.
 *
 * Each method returns a {@see Builder}. Chain ->amount(...), and for Bill
 * Payment also ->ref1(...) / ->ref2(...), then call ->build() to produce a
 * {@see Payload}.
 *
 * Example:
 *   $payload = ThaiPromptpay::phone('0899999999')->amount(420)->build();
 *   echo $payload;            // EMVCo TLV string
 *   $svg = $payload->svg();   // SVG QR markup
 *
 *   $bill = ThaiPromptpay::billPayment('099400015804189')
 *       ->ref1('INV001')
 *       ->ref2('CUST123')
 *       ->amount(1500)
 *       ->build();
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
}
