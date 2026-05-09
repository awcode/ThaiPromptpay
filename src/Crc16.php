<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay;

/**
 * CRC-16/CCITT-FALSE.
 *
 * Polynomial 0x1021, initial value 0xFFFF, no reflection, no XOR-out.
 * This is the checksum required by the EMVCo merchant-presented QR spec
 * used by PromptPay (tag 63).
 */
final class Crc16
{
    public static function ccittFalse(string $data): string
    {
        $crc = 0xFFFF;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
