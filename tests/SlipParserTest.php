<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Tests;

use Awcode\ThaiPromptpay\Crc16;
use Awcode\ThaiPromptpay\Slip\Exceptions\InvalidSlipException;
use Awcode\ThaiPromptpay\Slip\Parser;
use Awcode\ThaiPromptpay\Slip\SlipQr;
use Awcode\ThaiPromptpay\ThaiPromptpay;
use PHPUnit\Framework\TestCase;

class SlipParserTest extends TestCase
{
    public function test_parses_standard_slip_verify_payload(): void
    {
        $payload = $this->buildSlipPayload('014', '20240509SCB1234567890ABC');

        $slip = Parser::parse($payload);

        $this->assertSame('000001', $slip->apiId);
        $this->assertSame('014', $slip->sendingBank);
        $this->assertSame('20240509SCB1234567890ABC', $slip->transRef);
        $this->assertSame('SCB', $slip->bankShortName);
        $this->assertSame('Siam Commercial Bank', $slip->bankNameEnglish);
        $this->assertTrue($slip->isStandardSlipVerify());
        $this->assertFalse($slip->isTrueMoney());
        $this->assertSame([], $slip->extra);
        $this->assertSame($payload, $slip->payload);
    }

    public function test_resolves_each_known_bank_code(): void
    {
        $cases = [
            '002' => 'BBL',
            '004' => 'KBANK',
            '006' => 'KTB',
            '011' => 'TTB',
            '014' => 'SCB',
            '025' => 'BAY',
            '030' => 'GSB',
            '034' => 'BAAC',
            '067' => 'TISCO',
        ];

        foreach ($cases as $code => $expectedShort) {
            $slip = Parser::parse($this->buildSlipPayload($code, 'REF' . $code));
            $this->assertSame($expectedShort, $slip->bankShortName, "code {$code}");
        }
    }

    public function test_unknown_bank_code_returns_null_bank_metadata(): void
    {
        $slip = Parser::parse($this->buildSlipPayload('999', 'REF999'));

        $this->assertSame('999', $slip->sendingBank);
        $this->assertNull($slip->bankShortName);
        $this->assertNull($slip->bankNameEnglish);
        $this->assertNull($slip->bankNameThai);
    }

    public function test_invalid_crc_throws(): void
    {
        $payload = $this->buildSlipPayload('014', 'TX001');
        $tampered = substr($payload, 0, -4) . '0000';

        $this->expectException(InvalidSlipException::class);
        $this->expectExceptionMessage('Slip CRC mismatch');
        Parser::parse($tampered);
    }

    public function test_missing_sub_tags_throw(): void
    {
        // Tag 00 with only sub 00, no sub 01 or 02
        $inner = '0006000001';
        $tag00 = '00' . str_pad((string) strlen($inner), 2, '0', STR_PAD_LEFT) . $inner;
        $body = $tag00 . '5102TH9104';
        $payload = $body . Crc16::ccittFalse($body);

        $this->expectException(InvalidSlipException::class);
        $this->expectExceptionMessage('missing required sub-tags');
        Parser::parse($payload);
    }

    public function test_truncated_tlv_throws(): void
    {
        $this->expectException(InvalidSlipException::class);
        Parser::parse('001234');
    }

    public function test_unknown_api_id_throws(): void
    {
        // Tag 00 with sub 00 = "FOOBAR" (6 chars but not "000001"), valid sub 01 / 02
        $inner = '0006FOOBAR' . '0103014' . '02050ABCD';
        $tag00 = '00' . str_pad((string) strlen($inner), 2, '0', STR_PAD_LEFT) . $inner;
        $body = $tag00 . '5102TH9104';
        $payload = $body . Crc16::ccittFalse($body);

        $this->expectException(InvalidSlipException::class);
        $this->expectExceptionMessage('Unrecognised slip API ID');
        Parser::parse($payload);
    }

    public function test_truemoney_variant_is_recognised(): void
    {
        $sub00 = '00' . '02' . '01';                                  // api id "01"
        $sub01 = '01' . '03' . 'TMN';                                 // sending bank
        $sub02 = '02' . '06' . 'TM0001';                              // trans ref
        $sub03 = '03' . '08' . 'TRANSFER';                            // event type
        $inner = $sub00 . $sub01 . $sub02 . $sub03;
        $tag00 = '00' . str_pad((string) strlen($inner), 2, '0', STR_PAD_LEFT) . $inner;
        $body = $tag00 . '9104';
        $payload = $body . Crc16::ccittFalse($body);

        $slip = Parser::parse($payload);

        $this->assertTrue($slip->isTrueMoney());
        $this->assertSame('01', $slip->apiId);
        $this->assertSame('TMN', $slip->sendingBank);
        $this->assertSame('TM0001', $slip->transRef);
        $this->assertArrayHasKey('03', $slip->extra);
        $this->assertSame('TRANSFER', $slip->extra['03']);
    }

    public function test_to_array_shape(): void
    {
        $slip = Parser::parse($this->buildSlipPayload('004', 'KB123'));

        $array = $slip->toArray();

        $this->assertSame('000001', $array['api_id']);
        $this->assertSame('004', $array['sending_bank']);
        $this->assertSame('KB123', $array['trans_ref']);
        $this->assertSame('KBANK', $array['bank']['short']);
        $this->assertSame('Kasikornbank', $array['bank']['name_en']);
        $this->assertFalse($array['is_truemoney']);
    }

    public function test_facade_parse_slip(): void
    {
        $payload = $this->buildSlipPayload('014', 'TXFAC1');

        $slip = ThaiPromptpay::parseSlip($payload);

        $this->assertInstanceOf(SlipQr::class, $slip);
        $this->assertSame('SCB', $slip->bankShortName);
    }

    public function test_read_slip_auto_detects_payload_string(): void
    {
        $payload = $this->buildSlipPayload('014', 'TXAUTO');

        $slip = ThaiPromptpay::readSlip($payload);

        $this->assertSame('TXAUTO', $slip->transRef);
    }

    /**
     * Helper: construct a valid ITMX slip-verify Mini-QR payload (with CRC).
     */
    private function buildSlipPayload(string $bankCode, string $transRef): string
    {
        $sub00 = '00' . '06' . '000001';
        $sub01 = '01' . str_pad((string) strlen($bankCode), 2, '0', STR_PAD_LEFT) . $bankCode;
        $sub02 = '02' . str_pad((string) strlen($transRef), 2, '0', STR_PAD_LEFT) . $transRef;
        $inner = $sub00 . $sub01 . $sub02;
        $tag00 = '00' . str_pad((string) strlen($inner), 2, '0', STR_PAD_LEFT) . $inner;
        $body = $tag00 . '5102TH9104';

        return $body . Crc16::ccittFalse($body);
    }
}
