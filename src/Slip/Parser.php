<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip;

use Awcode\ThaiPromptpay\Crc16;
use Awcode\ThaiPromptpay\Slip\Exceptions\InvalidSlipException;

/**
 * Parses the EMVCo TLV "Slip Verify Mini QR" used by every Thai bank
 * (and TrueMoney's variant) into a {@see SlipQr} value object.
 *
 * Standard format (per the SCB-published, ITMX-aligned spec):
 *   Tag 00 = sub-template containing:
 *     Sub 00 = "000001"  (API ID — slip verify)
 *     Sub 01 = 3-digit ITMX bank code
 *     Sub 02 = transaction reference (ASCII, ≤25 chars)
 *   Tag 51 = "TH"
 *   Tag 91 = CRC-16/CCITT-FALSE (4 uppercase hex chars)
 *
 * TrueMoney variant (no Tag 51, Sub 00 = "01", extra sub-tags carrying
 * eventType/transactionId/date) is also supported.
 */
final class Parser
{
    private const ID_PAYLOAD = '00';
    private const ID_COUNTRY = '51';
    private const ID_CRC = '91';

    private const SUB_API_ID = '00';
    private const SUB_SENDING_BANK = '01';
    private const SUB_TRANS_REF = '02';

    public static function parse(string $payload): SlipQr
    {
        $payload = trim($payload);
        if ($payload === '') {
            throw new InvalidSlipException('Slip payload is empty.');
        }

        $tlv = self::tokenize($payload);

        if (! isset($tlv[self::ID_PAYLOAD])) {
            throw new InvalidSlipException(
                'Not a slip-verify Mini-QR: missing Tag 00 (payload sub-template).'
            );
        }

        $sub = self::tokenize($tlv[self::ID_PAYLOAD]);

        if (! isset($sub[self::SUB_API_ID], $sub[self::SUB_SENDING_BANK], $sub[self::SUB_TRANS_REF])) {
            throw new InvalidSlipException(
                'Slip payload missing required sub-tags (00=API ID, 01=bank, 02=transRef).'
            );
        }

        $apiId = $sub[self::SUB_API_ID];
        $sendingBank = $sub[self::SUB_SENDING_BANK];
        $transRef = $sub[self::SUB_TRANS_REF];

        if ($apiId !== SlipQr::API_ID_SLIP_VERIFY && $apiId !== SlipQr::API_ID_TRUEMONEY) {
            throw new InvalidSlipException(
                "Unrecognised slip API ID: {$apiId} (expected '000001' or '01')."
            );
        }

        if (isset($tlv[self::ID_CRC])) {
            self::validateCrc($payload, $tlv[self::ID_CRC]);
        }

        $extra = $sub;
        unset($extra[self::SUB_API_ID], $extra[self::SUB_SENDING_BANK], $extra[self::SUB_TRANS_REF]);

        $bank = BankCodes::lookup($sendingBank);

        return new SlipQr(
            apiId: $apiId,
            sendingBank: $sendingBank,
            transRef: $transRef,
            bankShortName: $bank['short'] ?? null,
            bankNameEnglish: $bank['name_en'] ?? null,
            bankNameThai: $bank['name_th'] ?? null,
            extra: $extra,
            payload: $payload,
        );
    }

    /**
     * Walk a TLV string into [tag => value]. Stops at end of input. Throws
     * if a tag/length runs off the end.
     *
     * @return array<string, string>
     */
    private static function tokenize(string $data): array
    {
        $out = [];
        $pos = 0;
        $len = strlen($data);

        while ($pos < $len) {
            if ($pos + 4 > $len) {
                throw new InvalidSlipException(
                    "Truncated TLV at offset {$pos}: expected 4 header bytes, found " . ($len - $pos) . '.'
                );
            }

            $tag = substr($data, $pos, 2);
            $lengthStr = substr($data, $pos + 2, 2);

            if (! ctype_digit($lengthStr)) {
                throw new InvalidSlipException(
                    "Invalid TLV length '{$lengthStr}' at offset " . ($pos + 2) . '.'
                );
            }
            $length = (int) $lengthStr;

            $valueStart = $pos + 4;
            if ($valueStart + $length > $len) {
                throw new InvalidSlipException(
                    "Truncated TLV value for tag {$tag} at offset {$valueStart}: declared {$length}, available "
                    . ($len - $valueStart) . '.'
                );
            }

            $out[$tag] = substr($data, $valueStart, $length);
            $pos = $valueStart + $length;
        }

        return $out;
    }

    private static function validateCrc(string $payload, string $declaredCrc): void
    {
        // CRC is computed over everything up to and including the literal "9104"
        // header (= ID_CRC + length). The CRC value itself is excluded.
        $crcMarker = self::ID_CRC . '04';
        $markerPos = strrpos($payload, $crcMarker);

        if ($markerPos === false || $markerPos + 4 + 4 !== strlen($payload)) {
            throw new InvalidSlipException(
                'Slip CRC tag (91) does not appear at the expected position at the end of the payload.'
            );
        }

        $body = substr($payload, 0, $markerPos + 4);
        $computed = Crc16::ccittFalse($body);

        if (strtoupper($declaredCrc) !== $computed) {
            throw new InvalidSlipException(
                "Slip CRC mismatch: payload claims '{$declaredCrc}', computed '{$computed}'."
            );
        }
    }
}
