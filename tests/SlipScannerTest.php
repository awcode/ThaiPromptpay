<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Tests;

use Awcode\ThaiPromptpay\Crc16;
use Awcode\ThaiPromptpay\Slip\Exceptions\QrCodeNotFoundException;
use Awcode\ThaiPromptpay\Slip\Scanner;
use Awcode\ThaiPromptpay\ThaiPromptpay;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use PHPUnit\Framework\TestCase;

class SlipScannerTest extends TestCase
{
    protected function setUp(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Slip image rendering requires ext-imagick.');
        }
    }

    public function test_decodes_payload_from_rendered_png(): void
    {
        $payload = $this->buildSlipPayload('014', '20240509SCB1234567890ABC');
        $png = $this->renderPng($payload);

        $decoded = Scanner::decode($png);

        $this->assertSame($payload, $decoded);
    }

    public function test_facade_scan_slip_round_trips(): void
    {
        $payload = $this->buildSlipPayload('004', 'KB99887766');
        $png = $this->renderPng($payload);

        $slip = ThaiPromptpay::scanSlip($png);

        $this->assertSame('004', $slip->sendingBank);
        $this->assertSame('KBANK', $slip->bankShortName);
        $this->assertSame('KB99887766', $slip->transRef);
    }

    public function test_read_slip_auto_detects_image(): void
    {
        $payload = $this->buildSlipPayload('011', 'TTBABC');
        $png = $this->renderPng($payload);

        $slip = ThaiPromptpay::readSlip($png);

        $this->assertSame('TTB', $slip->bankShortName);
    }

    public function test_read_slip_from_file_path(): void
    {
        $payload = $this->buildSlipPayload('025', 'BAY1234');
        $png = $this->renderPng($payload);

        $tmp = tempnam(sys_get_temp_dir(), 'slip-') . '.png';
        file_put_contents($tmp, $png);

        try {
            $slip = ThaiPromptpay::scanSlip($tmp);
            $this->assertSame('BAY', $slip->bankShortName);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_data_uri_input(): void
    {
        $payload = $this->buildSlipPayload('006', 'KTBXYZ');
        $png = $this->renderPng($payload);
        $dataUri = 'data:image/png;base64,' . base64_encode($png);

        $slip = ThaiPromptpay::scanSlip($dataUri);

        $this->assertSame('KTB', $slip->bankShortName);
    }

    public function test_garbage_input_raises_qr_not_found(): void
    {
        $this->expectException(QrCodeNotFoundException::class);
        Scanner::decode(str_repeat("\0", 1024));
    }

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

    private function renderPng(string $payload, int $size = 400): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 4),
            new ImagickImageBackEnd()
        );

        return (new Writer($renderer))->writeString($payload);
    }
}
