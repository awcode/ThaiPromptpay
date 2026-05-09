<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Tests;

use Awcode\ThaiPromptpay\Builder;
use Awcode\ThaiPromptpay\Exceptions\InvalidTargetException;
use Awcode\ThaiPromptpay\ThaiPromptpay;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    /**
     * Byte-verified test vector from kittinan/php-promptpay-qr README.
     * Static QR (no amount) for phone 0899999999.
     */
    public function test_static_phone_payload_matches_reference(): void
    {
        $expected = '00020101021129370016A000000677010111011300668999999995802TH53037646304FE29';

        $payload = ThaiPromptpay::phone('0899999999')->build();

        $this->assertSame($expected, $payload->toString());
    }

    /**
     * Byte-verified test vector from kittinan/php-promptpay-qr README.
     * Dynamic QR for phone 089-999-9999 with amount 420.
     */
    public function test_dynamic_phone_with_amount_matches_reference(): void
    {
        $expected = '00020101021229370016A000000677010111011300668999999995802TH53037645406420.006304CF9E';

        $payload = ThaiPromptpay::phone('089-999-9999')->amount(420)->build();

        $this->assertSame($expected, $payload->toString());
    }

    public function test_phone_dashes_and_spaces_are_stripped(): void
    {
        $a = ThaiPromptpay::phone('0899999999')->build()->toString();
        $b = ThaiPromptpay::phone('089-999-9999')->build()->toString();
        $c = ThaiPromptpay::phone('089 999 9999')->build()->toString();

        $this->assertSame($a, $b);
        $this->assertSame($a, $c);
    }

    public function test_amount_is_formatted_to_two_decimals(): void
    {
        $this->assertStringContainsString(
            '5406420.00',
            ThaiPromptpay::phone('0899999999')->amount(420)->build()->toString()
        );

        $this->assertStringContainsString(
            '54071999.99',
            ThaiPromptpay::phone('0899999999')->amount(1999.99)->build()->toString()
        );

        $this->assertStringContainsString(
            '54044.20',
            ThaiPromptpay::phone('0899999999')->amount(4.2)->build()->toString()
        );

        $this->assertStringContainsString(
            '54071234.56',
            ThaiPromptpay::phone('0899999999')->amount('1234.56')->build()->toString()
        );
    }

    public function test_static_qr_when_no_amount(): void
    {
        $payload = ThaiPromptpay::phone('0899999999')->build()->toString();

        $this->assertStringStartsWith('000201010211', $payload);
    }

    public function test_dynamic_qr_when_amount_present(): void
    {
        $payload = ThaiPromptpay::phone('0899999999')->amount(100)->build()->toString();

        $this->assertStringStartsWith('000201010212', $payload);
    }

    public function test_national_id_uses_sub_id_02(): void
    {
        $payload = ThaiPromptpay::nationalId('1234567890123')->build()->toString();

        $this->assertStringContainsString('0016A000000677010111', $payload);
        $this->assertStringContainsString('02131234567890123', $payload);
    }

    public function test_ewallet_uses_sub_id_03(): void
    {
        $payload = ThaiPromptpay::eWallet('012345678901234')->build()->toString();

        $this->assertStringContainsString('0016A000000677010111', $payload);
        $this->assertStringContainsString('0315012345678901234', $payload);
    }

    public function test_bill_payment_uses_tag_30_with_ref1_and_ref2(): void
    {
        $payload = ThaiPromptpay::billPayment('099400015804189')
            ->ref1('INV001')
            ->ref2('CUST123')
            ->amount(1500)
            ->build()
            ->toString();

        // Tag 30, AID for domestic bill payment, biller ID, Ref1, Ref2.
        $this->assertStringStartsWith('000201010212', $payload);
        $this->assertStringContainsString('30', $payload);
        $this->assertStringContainsString('0016A000000677010112', $payload);
        $this->assertStringContainsString('0115099400015804189', $payload);
        $this->assertStringContainsString('0206INV001', $payload);
        $this->assertStringContainsString('0307CUST123', $payload);
        $this->assertStringContainsString('54071500.00', $payload);

        // CRC must verify: last 4 chars are CRC over everything before.
        $this->assertCrcValid($payload);
    }

    public function test_bill_payment_ref1_only(): void
    {
        $payload = ThaiPromptpay::billPayment('099400015804189')
            ->ref1('BILL2025')
            ->amount(99.50)
            ->build()
            ->toString();

        $this->assertStringContainsString('0208BILL2025', $payload);
        $this->assertStringNotContainsString('0307', $payload);
        $this->assertCrcValid($payload);
    }

    public function test_bill_payment_cross_border_uses_different_aid(): void
    {
        $payload = ThaiPromptpay::billPayment('099400015804189')
            ->ref1('INV001')
            ->crossBorder()
            ->build()
            ->toString();

        $this->assertStringContainsString('0016A000000677012006', $payload);
    }

    public function test_bill_payment_lowercase_ref_is_uppercased(): void
    {
        $payload = ThaiPromptpay::billPayment('099400015804189')
            ->ref1('inv001')
            ->ref2('cust123')
            ->build()
            ->toString();

        $this->assertStringContainsString('0206INV001', $payload);
        $this->assertStringContainsString('0307CUST123', $payload);
    }

    public function test_bill_payment_without_ref1_throws(): void
    {
        $this->expectException(\LogicException::class);
        ThaiPromptpay::billPayment('099400015804189')->amount(100)->build();
    }

    public function test_ref1_on_personal_qr_throws(): void
    {
        $this->expectException(\LogicException::class);
        ThaiPromptpay::phone('0899999999')->ref1('FOO');
    }

    public function test_invalid_phone_throws(): void
    {
        $this->expectException(InvalidTargetException::class);
        ThaiPromptpay::phone('123');
    }

    public function test_invalid_national_id_length_throws(): void
    {
        $this->expectException(InvalidTargetException::class);
        ThaiPromptpay::nationalId('12345');
    }

    public function test_invalid_ewallet_length_throws(): void
    {
        $this->expectException(InvalidTargetException::class);
        ThaiPromptpay::eWallet('1234567890');
    }

    public function test_ref_with_invalid_characters_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ThaiPromptpay::billPayment('099400015804189')->ref1('INV-001');
    }

    public function test_ref_too_long_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ThaiPromptpay::billPayment('099400015804189')->ref1(str_repeat('A', 21));
    }

    public function test_negative_amount_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ThaiPromptpay::phone('0899999999')->amount(-1);
    }

    public function test_builder_is_immutable(): void
    {
        $builder = Builder::forPhone('0899999999');
        $withAmount = $builder->amount(100);

        $this->assertNotSame($builder, $withAmount);
        $this->assertStringStartsWith('000201010211', $builder->build()->toString());
        $this->assertStringStartsWith('000201010212', $withAmount->build()->toString());
    }

    public function test_to_string_returns_payload(): void
    {
        $builder = ThaiPromptpay::phone('0899999999');

        $this->assertSame($builder->build()->toString(), (string) $builder);
    }

    private function assertCrcValid(string $payload): void
    {
        $body = substr($payload, 0, -4);
        $crc = substr($payload, -4);
        $computed = \Awcode\ThaiPromptpay\Crc16::ccittFalse($body);

        $this->assertSame($computed, $crc, "CRC mismatch on payload: {$payload}");
    }
}
