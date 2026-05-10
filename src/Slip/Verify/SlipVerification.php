<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip\Verify;

/**
 * Canonical, provider-agnostic result of verifying a slip via an aggregator.
 *
 * The same shape is returned regardless of which provider answered. Fields
 * that a given provider doesn't include are left null — the {@see $raw}
 * array always contains the original response if you need provider-specific
 * detail.
 */
final class SlipVerification
{
    /**
     * @param  string  $provider  'slipok' | 'easyslip' | ...
     * @param  array<string, mixed>  $raw  Original provider response, untouched.
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $transRef,
        public readonly \DateTimeImmutable $paidAt,
        public readonly float $amount,
        public readonly string $currency,
        public readonly Party $sender,
        public readonly Party $receiver,
        public readonly ?string $sendingBankCode = null,
        public readonly ?string $receivingBankCode = null,
        public readonly ?string $ref1 = null,
        public readonly ?string $ref2 = null,
        public readonly ?string $ref3 = null,
        public readonly ?float $fee = null,
        public readonly array $raw = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'trans_ref' => $this->transRef,
            'paid_at' => $this->paidAt->format(DATE_ATOM),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'sending_bank_code' => $this->sendingBankCode,
            'receiving_bank_code' => $this->receivingBankCode,
            'sender' => $this->sender->toArray(),
            'receiver' => $this->receiver->toArray(),
            'ref1' => $this->ref1,
            'ref2' => $this->ref2,
            'ref3' => $this->ref3,
            'fee' => $this->fee,
        ];
    }
}
