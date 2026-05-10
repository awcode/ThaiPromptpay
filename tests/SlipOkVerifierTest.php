<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Tests;

use Awcode\ThaiPromptpay\Slip\Verify\Exceptions\VerificationException;
use Awcode\ThaiPromptpay\Slip\Verify\SlipOkVerifier;
use Awcode\ThaiPromptpay\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

class SlipOkVerifierTest extends TestCase
{
    public function test_normalises_a_successful_response(): void
    {
        $transport = new FakeTransport();
        $transport->response = [
            'success' => true,
            'data' => [
                'success' => true,
                'language' => 'EN',
                'transRef' => '20240509SCB0001',
                'transDate' => '20240509',
                'transTime' => '15:30:00',
                'transTimestamp' => '2024-05-09T08:30:00.000Z',
                'sendingBank' => '014',
                'receivingBank' => '004',
                'amount' => 1500.00,
                'transFeeAmount' => 0,
                'ref1' => 'INV001',
                'ref2' => 'CUST123',
                'ref3' => '',
                'sender' => [
                    'displayName' => 'นาย ทดสอบ',
                    'name' => 'MR TESTING',
                    'proxy' => ['type' => 'MSISDN', 'value' => 'xxx-xxx-9999'],
                    'account' => ['type' => 'BANKAC', 'value' => 'xxx-x-x1234-x'],
                ],
                'receiver' => [
                    'displayName' => 'ร้านทดสอบ',
                    'name' => 'TEST SHOP',
                    'proxy' => ['type' => 'BILLERID', 'value' => '099400015804189'],
                    'account' => ['type' => 'BANKAC', 'value' => 'xxx-x-x5678-x'],
                ],
            ],
        ];

        $verifier = new SlipOkVerifier('test-key', 'shop-1', $transport);
        $result = $verifier->verify($this->buildPayload());

        $this->assertSame('slipok', $result->provider);
        $this->assertSame('20240509SCB0001', $result->transRef);
        $this->assertEqualsWithDelta(1500.0, $result->amount, 0.001);
        $this->assertSame('THB', $result->currency);
        $this->assertSame('014', $result->sendingBankCode);
        $this->assertSame('004', $result->receivingBankCode);
        $this->assertSame('INV001', $result->ref1);
        $this->assertSame('CUST123', $result->ref2);
        $this->assertNull($result->ref3);
        $this->assertSame(0.0, $result->fee);
        $this->assertSame('2024-05-09T08:30:00+00:00', $result->paidAt->format(DATE_ATOM));

        $this->assertSame('นาย ทดสอบ', $result->sender->name);
        $this->assertSame('MR TESTING', $result->sender->nameEn);
        $this->assertSame('xxx-x-x1234-x', $result->sender->accountNumber);
        $this->assertSame('MSISDN', $result->sender->proxyType);

        $this->assertSame('TEST SHOP', $result->receiver->nameEn);
        $this->assertSame('xxx-x-x5678-x', $result->receiver->accountNumber);
    }

    public function test_request_uses_correct_url_headers_and_body(): void
    {
        $transport = new FakeTransport();
        $transport->response = ['success' => true, 'data' => $this->minimalSuccessData()];

        $verifier = new SlipOkVerifier('SECRET-KEY-123', 'branch-99', $transport);
        $payload = $this->buildPayload();
        $verifier->verify($payload);

        $this->assertCount(1, $transport->calls);
        $call = $transport->calls[0];

        $this->assertSame('https://api.slipok.com/api/line/apikey/branch-99', $call['url']);
        $this->assertSame('SECRET-KEY-123', $call['headers']['x-authorization']);
        $this->assertSame($payload, $call['body']['data']);
        $this->assertTrue($call['body']['log']);
    }

    public function test_error_response_throws_verification_exception(): void
    {
        $transport = new FakeTransport();
        $transport->status = 400;
        $transport->response = ['success' => false, 'code' => 1011, 'message' => 'Slip not found.', 'data' => null];

        $verifier = new SlipOkVerifier('test-key', 'shop-1', $transport);

        try {
            $verifier->verify($this->buildPayload());
            $this->fail('Expected VerificationException.');
        } catch (VerificationException $e) {
            $this->assertSame('slipok', $e->provider);
            $this->assertSame(400, $e->httpStatus);
            $this->assertSame(1011, $e->response['code']);
            $this->assertStringContainsString('1011', $e->getMessage());
            $this->assertStringContainsString('Slip not found', $e->getMessage());
        }
    }

    public function test_log_slips_can_be_disabled(): void
    {
        $transport = new FakeTransport();
        $transport->response = ['success' => true, 'data' => $this->minimalSuccessData()];

        $verifier = new SlipOkVerifier('test-key', 'shop-1', $transport, logSlips: false);
        $verifier->verify($this->buildPayload());

        $this->assertFalse($transport->calls[0]['body']['log']);
    }

    public function test_constructor_validates_credentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SlipOkVerifier('', 'shop');
    }

    /** @return array<string, mixed> */
    private function minimalSuccessData(): array
    {
        return [
            'success' => true,
            'transRef' => 'TX001',
            'transTimestamp' => '2024-05-09T08:30:00.000Z',
            'sendingBank' => '014',
            'receivingBank' => '004',
            'amount' => 100.0,
            'sender' => ['displayName' => 'A', 'name' => 'A', 'account' => ['type' => 'BANKAC', 'value' => 'x']],
            'receiver' => ['displayName' => 'B', 'name' => 'B', 'account' => ['type' => 'BANKAC', 'value' => 'y']],
        ];
    }

    private function buildPayload(): string
    {
        $transRef = 'TX0001';
        $sub00 = '00' . '06' . '000001';
        $sub01 = '01' . '03' . '014';
        $sub02 = '02' . str_pad((string) strlen($transRef), 2, '0', STR_PAD_LEFT) . $transRef;
        $inner = $sub00 . $sub01 . $sub02;
        $tag00 = '00' . str_pad((string) strlen($inner), 2, '0', STR_PAD_LEFT) . $inner;
        $body = $tag00 . '5102TH9104';

        return $body . \Awcode\ThaiPromptpay\Crc16::ccittFalse($body);
    }
}
