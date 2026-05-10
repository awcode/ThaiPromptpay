<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Tests;

use Awcode\ThaiPromptpay\Slip\Verify\EasySlipVerifier;
use Awcode\ThaiPromptpay\Slip\Verify\Exceptions\VerificationException;
use Awcode\ThaiPromptpay\Tests\Support\FakeTransport;
use PHPUnit\Framework\TestCase;

class EasySlipVerifierTest extends TestCase
{
    public function test_normalises_a_v2_success_response(): void
    {
        $transport = new FakeTransport();
        $transport->response = [
            'success' => true,
            'data' => [
                'remark' => null,
                'isDuplicate' => false,
                'rawSlip' => [
                    'payload' => 'echo',
                    'transRef' => 'EZ987654321',
                    'date' => '2024-05-09T15:30:00+07:00',
                    'countryCode' => 'TH',
                    'amount' => [
                        'amount' => 1500.50,
                        'local' => ['amount' => 0, 'currency' => ''],
                    ],
                    'fee' => 0,
                    'ref1' => 'INV001',
                    'ref2' => '',
                    'ref3' => '',
                    'sender' => [
                        'bank' => ['id' => '014', 'name' => 'ไทยพาณิชย์', 'short' => 'SCB'],
                        'account' => [
                            'name' => ['th' => 'นาย ทดสอบ', 'en' => 'MR TESTING'],
                            'bank' => ['type' => 'BANKAC', 'account' => 'xxx-x-x1234-x'],
                        ],
                    ],
                    'receiver' => [
                        'bank' => ['id' => '004', 'name' => 'กสิกรไทย', 'short' => 'KBANK'],
                        'account' => [
                            'name' => ['th' => 'ร้านทดสอบ', 'en' => 'TEST SHOP'],
                            'bank' => ['type' => 'BANKAC', 'account' => 'xxx-x-x5678-x'],
                        ],
                        'merchantId' => 'M-99999',
                    ],
                ],
            ],
            'message' => 'Verified',
        ];

        $verifier = new EasySlipVerifier('test-token', $transport);
        $result = $verifier->verify($this->buildPayload());

        $this->assertSame('easyslip', $result->provider);
        $this->assertSame('EZ987654321', $result->transRef);
        $this->assertEqualsWithDelta(1500.50, $result->amount, 0.001);
        $this->assertSame('THB', $result->currency);
        $this->assertSame('2024-05-09T15:30:00+07:00', $result->paidAt->format(DATE_ATOM));
        $this->assertSame('014', $result->sendingBankCode);
        $this->assertSame('004', $result->receivingBankCode);
        $this->assertSame('INV001', $result->ref1);
        $this->assertNull($result->ref2);

        $this->assertSame('นาย ทดสอบ', $result->sender->name);
        $this->assertSame('SCB', $result->sender->bankShort);
        $this->assertSame('ไทยพาณิชย์', $result->sender->bankName);
        $this->assertSame('xxx-x-x1234-x', $result->sender->accountNumber);

        $this->assertSame('KBANK', $result->receiver->bankShort);
        $this->assertSame('TEST SHOP', $result->receiver->nameEn);
    }

    public function test_request_uses_correct_url_headers_and_body(): void
    {
        $transport = new FakeTransport();
        $transport->response = ['success' => true, 'data' => $this->minimalEnvelope()];

        $verifier = new EasySlipVerifier('SECRET-TOKEN-XYZ', $transport, checkDuplicate: true);
        $payload = $this->buildPayload();
        $verifier->verify($payload);

        $call = $transport->calls[0];
        $this->assertSame('https://api.easyslip.com/v2/verify/bank', $call['url']);
        $this->assertSame('Bearer SECRET-TOKEN-XYZ', $call['headers']['Authorization']);
        $this->assertSame($payload, $call['body']['payload']);
        $this->assertTrue($call['body']['checkDuplicate']);
    }

    public function test_check_duplicate_off_by_default(): void
    {
        $transport = new FakeTransport();
        $transport->response = ['success' => true, 'data' => $this->minimalEnvelope()];

        $verifier = new EasySlipVerifier('test-token', $transport);
        $verifier->verify($this->buildPayload());

        $this->assertArrayNotHasKey('checkDuplicate', $transport->calls[0]['body']);
    }

    public function test_error_response_throws_verification_exception(): void
    {
        $transport = new FakeTransport();
        $transport->status = 400;
        $transport->response = [
            'success' => false,
            'error' => ['code' => 'SLIP_NOT_FOUND', 'message' => 'The slip could not be located.'],
        ];

        $verifier = new EasySlipVerifier('test-token', $transport);

        try {
            $verifier->verify($this->buildPayload());
            $this->fail('Expected VerificationException.');
        } catch (VerificationException $e) {
            $this->assertSame('easyslip', $e->provider);
            $this->assertSame(400, $e->httpStatus);
            $this->assertStringContainsString('SLIP_NOT_FOUND', $e->getMessage());
        }
    }

    public function test_constructor_validates_credentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EasySlipVerifier('');
    }

    /** @return array<string, mixed> */
    private function minimalEnvelope(): array
    {
        return [
            'isDuplicate' => false,
            'rawSlip' => [
                'transRef' => 'TX001',
                'date' => '2024-05-09T15:30:00+07:00',
                'amount' => ['amount' => 100, 'local' => ['amount' => 0, 'currency' => '']],
                'fee' => 0,
                'sender' => ['bank' => ['id' => '014', 'short' => 'SCB']],
                'receiver' => ['bank' => ['id' => '004', 'short' => 'KBANK']],
            ],
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
