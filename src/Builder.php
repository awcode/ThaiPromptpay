<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay;

use Awcode\ThaiPromptpay\Exceptions\InvalidTargetException;

/**
 * Fluent builder for a PromptPay EMVCo payload.
 *
 * Use the named constructors for the kind of recipient you have:
 *   Builder::forPhone('0899999999')
 *   Builder::forNationalId('1234567890123')
 *   Builder::forEWallet('012345678901234')
 *   Builder::forBillPayment('099400015804189')->ref1('INV001')->ref2('CUST123')
 *
 * Then chain ->amount(...) for a dynamic QR, and ->build() to get a Payload.
 */
final class Builder
{
    private const TYPE_PHONE = 'phone';
    private const TYPE_NATIONAL_ID = 'national_id';
    private const TYPE_EWALLET = 'ewallet';
    private const TYPE_BILLER = 'biller';

    /** Application Identifier strings (EMV AID / GUID). */
    private const AID_PROMPTPAY_MERCHANT = 'A000000677010111';
    private const AID_BILLPAY_DOMESTIC = 'A000000677010112';
    private const AID_BILLPAY_CROSSBORDER = 'A000000677012006';

    /** Top-level field IDs (EMVCo merchant-presented QR). */
    private const ID_PAYLOAD_FORMAT = '00';
    private const ID_POI_METHOD = '01';
    private const ID_MERCHANT_INFO_PROMPTPAY = '29';
    private const ID_MERCHANT_INFO_BILLPAYMENT = '30';
    private const ID_COUNTRY = '58';
    private const ID_CURRENCY = '53';
    private const ID_AMOUNT = '54';
    private const ID_CRC = '63';

    private const POI_STATIC = '11';
    private const POI_DYNAMIC = '12';

    private string $type;
    private string $target;
    private ?string $amount = null;
    private ?string $ref1 = null;
    private ?string $ref2 = null;
    private bool $crossBorder = false;

    private function __construct(string $type, string $target)
    {
        $this->type = $type;
        $this->target = $target;
    }

    public static function forPhone(string $phone): self
    {
        $digits = self::digitsOnly($phone);
        if ($digits === '' || strlen($digits) < 9 || strlen($digits) > 12) {
            throw new InvalidTargetException(
                "Phone number must be 9-12 digits, got: {$phone}"
            );
        }

        return new self(self::TYPE_PHONE, self::formatPhone($digits));
    }

    public static function forNationalId(string $id): self
    {
        $digits = self::digitsOnly($id);
        if (strlen($digits) !== 13) {
            throw new InvalidTargetException(
                "National ID / Tax ID must be 13 digits, got: {$id}"
            );
        }

        return new self(self::TYPE_NATIONAL_ID, $digits);
    }

    public static function forEWallet(string $id): self
    {
        $digits = self::digitsOnly($id);
        if (strlen($digits) !== 15) {
            throw new InvalidTargetException(
                "e-Wallet ID must be 15 digits, got: {$id}"
            );
        }

        return new self(self::TYPE_EWALLET, $digits);
    }

    public static function forBillPayment(string $billerId): self
    {
        $digits = self::digitsOnly($billerId);
        if (strlen($digits) < 13 || strlen($digits) > 15) {
            throw new InvalidTargetException(
                "Biller ID must be 13-15 digits, got: {$billerId}"
            );
        }

        return new self(self::TYPE_BILLER, $digits);
    }

    public function amount(float|int|string $amount): self
    {
        $value = is_string($amount) ? (float) $amount : $amount;
        if ($value < 0) {
            throw new \InvalidArgumentException("Amount must be non-negative, got: {$amount}");
        }
        $clone = clone $this;
        $clone->amount = number_format((float) $value, 2, '.', '');

        return $clone;
    }

    public function ref1(string $ref1): self
    {
        $this->assertBiller('ref1');
        $clone = clone $this;
        $clone->ref1 = self::sanitizeReference($ref1, 'Ref1');

        return $clone;
    }

    public function ref2(string $ref2): self
    {
        $this->assertBiller('ref2');
        $clone = clone $this;
        $clone->ref2 = self::sanitizeReference($ref2, 'Ref2');

        return $clone;
    }

    public function crossBorder(bool $crossBorder = true): self
    {
        $this->assertBiller('crossBorder');
        $clone = clone $this;
        $clone->crossBorder = $crossBorder;

        return $clone;
    }

    public function build(): Payload
    {
        if ($this->type === self::TYPE_BILLER && $this->ref1 === null) {
            throw new \LogicException(
                'Bill Payment requires a Ref1. Call ->ref1(...) before ->build().'
            );
        }

        $merchantInfo = $this->type === self::TYPE_BILLER
            ? $this->buildBillPaymentBlock()
            : $this->buildCreditTransferBlock();

        $parts = [];
        $parts[] = self::tlv(self::ID_PAYLOAD_FORMAT, '01');
        $parts[] = self::tlv(self::ID_POI_METHOD, $this->amount === null ? self::POI_STATIC : self::POI_DYNAMIC);
        $parts[] = $merchantInfo;
        $parts[] = self::tlv(self::ID_COUNTRY, 'TH');
        $parts[] = self::tlv(self::ID_CURRENCY, '764');
        if ($this->amount !== null) {
            $parts[] = self::tlv(self::ID_AMOUNT, $this->amount);
        }

        $body = implode('', $parts);
        $crcInput = $body . self::ID_CRC . '04';
        $crc = Crc16::ccittFalse($crcInput);

        return new Payload($crcInput . $crc);
    }

    public function __toString(): string
    {
        return $this->build()->toString();
    }

    private function buildCreditTransferBlock(): string
    {
        $aid = self::tlv('00', self::AID_PROMPTPAY_MERCHANT);

        $entry = match ($this->type) {
            self::TYPE_PHONE => self::tlv('01', $this->target),
            self::TYPE_NATIONAL_ID => self::tlv('02', $this->target),
            self::TYPE_EWALLET => self::tlv('03', $this->target),
        };

        return self::tlv(self::ID_MERCHANT_INFO_PROMPTPAY, $aid . $entry);
    }

    private function buildBillPaymentBlock(): string
    {
        $aid = self::tlv(
            '00',
            $this->crossBorder ? self::AID_BILLPAY_CROSSBORDER : self::AID_BILLPAY_DOMESTIC
        );
        $biller = self::tlv('01', $this->target);
        $ref1 = self::tlv('02', (string) $this->ref1);
        $inner = $aid . $biller . $ref1;
        if ($this->ref2 !== null) {
            $inner .= self::tlv('03', $this->ref2);
        }

        return self::tlv(self::ID_MERCHANT_INFO_BILLPAYMENT, $inner);
    }

    private static function tlv(string $id, string $value): string
    {
        return $id . str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    private static function digitsOnly(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    /**
     * Convert a 9–12 digit Thai phone number to the 13-digit form used in
     * PromptPay payloads: leading 0 → 66, then left-padded to 13 digits.
     *
     *   "0899999999" → "0066899999999"
     *   "899999999"  → "0000899999999"  (no leading zero, just zero-padded)
     */
    private static function formatPhone(string $digits): string
    {
        if (str_starts_with($digits, '0')) {
            $digits = '66' . substr($digits, 1);
        }

        return substr('0000000000000' . $digits, -13);
    }

    /**
     * BoT spec for Bill Payment Ref1/Ref2: alphanumeric, max 20 chars.
     * Bank apps are case-insensitive but we uppercase for canonical form.
     */
    private static function sanitizeReference(string $reference, string $name): string
    {
        $trimmed = trim($reference);
        if ($trimmed === '') {
            throw new \InvalidArgumentException("{$name} cannot be empty.");
        }
        if (strlen($trimmed) > 20) {
            throw new \InvalidArgumentException("{$name} must be 20 characters or fewer.");
        }
        if (! preg_match('/^[A-Za-z0-9]+$/', $trimmed)) {
            throw new \InvalidArgumentException("{$name} must be alphanumeric (A-Z, 0-9).");
        }

        return strtoupper($trimmed);
    }

    private function assertBiller(string $method): void
    {
        if ($this->type !== self::TYPE_BILLER) {
            throw new \LogicException(
                "->{$method}() is only valid for Bill Payment QRs (use Builder::forBillPayment)."
            );
        }
    }
}
