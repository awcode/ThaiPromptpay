<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Tests;

use Awcode\ThaiPromptpay\Crc16;
use PHPUnit\Framework\TestCase;

class Crc16Test extends TestCase
{
    /** Standard CRC-16/CCITT-FALSE check vector: "123456789" → 0x29B1. */
    public function test_standard_check_vector(): void
    {
        $this->assertSame('29B1', Crc16::ccittFalse('123456789'));
    }

    /** Empty string → initial value 0xFFFF. */
    public function test_empty_string(): void
    {
        $this->assertSame('FFFF', Crc16::ccittFalse(''));
    }

    /**
     * Smoking-gun vector from kittinan/php-promptpay-qr README.
     * The CRC over everything before the final 4 hex chars must equal "FE29".
     */
    public function test_promptpay_static_vector(): void
    {
        $body = '00020101021129370016A000000677010111011300668999999995802TH53037646304';

        $this->assertSame('FE29', Crc16::ccittFalse($body));
    }
}
