<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip\Verify;

/**
 * One side of a transfer (sender or receiver) as returned by an aggregator.
 *
 * Different providers populate different fields. Unset fields are null —
 * absence means "the provider didn't return it", not "empty".
 */
final class Party
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $nameTh = null,
        public readonly ?string $nameEn = null,
        public readonly ?string $bankCode = null,
        public readonly ?string $bankShort = null,
        public readonly ?string $bankName = null,
        public readonly ?string $accountNumber = null,
        public readonly ?string $accountType = null,
        public readonly ?string $proxyType = null,
        public readonly ?string $proxyValue = null,
    ) {
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'name_th' => $this->nameTh,
            'name_en' => $this->nameEn,
            'bank_code' => $this->bankCode,
            'bank_short' => $this->bankShort,
            'bank_name' => $this->bankName,
            'account_number' => $this->accountNumber,
            'account_type' => $this->accountType,
            'proxy_type' => $this->proxyType,
            'proxy_value' => $this->proxyValue,
        ];
    }
}
