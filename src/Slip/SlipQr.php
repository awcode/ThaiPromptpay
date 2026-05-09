<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip;

/**
 * Decoded contents of a Thai slip-verify Mini-QR.
 *
 * IMPORTANT: this is a parsed view of what the QR claims, NOT proof that the
 * underlying transaction occurred. Verifying that requires calling the
 * issuing bank's Open API with merchant credentials, which is out of scope
 * for this package.
 */
final class SlipQr
{
    public const API_ID_SLIP_VERIFY = '000001';
    public const API_ID_TRUEMONEY = '01';

    /**
     * @param  string  $apiId  Tag 00 / Sub 00. "000001" for the standard ITMX slip-verify format
     *                         used by every Thai bank, or "01" for the TrueMoney Wallet variant.
     * @param  string  $sendingBank  Tag 00 / Sub 01. 3-digit ITMX SMART code (e.g. "014" = SCB).
     *                               For TrueMoney slips this is "TMN".
     * @param  string  $transRef  Tag 00 / Sub 02. Up to 25 ASCII characters; bank-specific format.
     * @param  string|null  $bankShortName  Resolved bank short name (e.g. "SCB"), or null if unknown.
     * @param  string|null  $bankNameEnglish  Resolved English name (e.g. "Siam Commercial Bank").
     * @param  string|null  $bankNameThai  Resolved Thai name.
     * @param  array<string, string>  $extra  Any extra TLV sub-tags found inside Tag 00 (forward-compat).
     * @param  string  $payload  Original payload string.
     */
    public function __construct(
        public readonly string $apiId,
        public readonly string $sendingBank,
        public readonly string $transRef,
        public readonly ?string $bankShortName,
        public readonly ?string $bankNameEnglish,
        public readonly ?string $bankNameThai,
        public readonly array $extra,
        public readonly string $payload,
    ) {
    }

    public function isStandardSlipVerify(): bool
    {
        return $this->apiId === self::API_ID_SLIP_VERIFY;
    }

    public function isTrueMoney(): bool
    {
        return $this->apiId === self::API_ID_TRUEMONEY;
    }

    /**
     * @return array{
     *     api_id: string,
     *     sending_bank: string,
     *     bank: array{short: string, name_en: string, name_th: string}|null,
     *     trans_ref: string,
     *     is_truemoney: bool,
     *     extra: array<string, string>,
     *     payload: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'api_id' => $this->apiId,
            'sending_bank' => $this->sendingBank,
            'bank' => $this->bankShortName === null ? null : [
                'short' => $this->bankShortName,
                'name_en' => $this->bankNameEnglish ?? '',
                'name_th' => $this->bankNameThai ?? '',
            ],
            'trans_ref' => $this->transRef,
            'is_truemoney' => $this->isTrueMoney(),
            'extra' => $this->extra,
            'payload' => $this->payload,
        ];
    }
}
