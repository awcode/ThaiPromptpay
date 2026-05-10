<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip\Verify;

use Awcode\ThaiPromptpay\Slip\Verify\Exceptions\VerificationException;
use Awcode\ThaiPromptpay\Slip\Verify\Transport\Transport;

/**
 * SlipOK aggregator (slipok.com).
 *
 * Endpoint: POST https://api.slipok.com/api/line/apikey/{branchId}
 * Auth:     x-authorization: <apiKey>
 *
 * Each shop/branch gets its own (apiKey, branchId) pair. Both go in the
 * config, with branchId in the URL path and apiKey in the header.
 *
 * @see https://slipok.com/api-documentation/check-slip/
 */
final class SlipOkVerifier extends AbstractVerifier
{
    public const PROVIDER = 'slipok';
    private const BASE_URL = 'https://api.slipok.com/api/line/apikey/';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $branchId,
        ?Transport $transport = null,
        private readonly bool $logSlips = true,
    ) {
        if ($apiKey === '' || $branchId === '') {
            throw new \InvalidArgumentException('SlipOK requires both an apiKey and a branchId.');
        }
        parent::__construct($transport);
    }

    public function verify(string $input): SlipVerification
    {
        $payload = $this->resolvePayload($input);

        $response = $this->transport->postJson(
            self::BASE_URL . rawurlencode($this->branchId),
            ['x-authorization' => $this->apiKey],
            ['data' => $payload, 'log' => $this->logSlips],
        );

        $body = $response['body'];
        $status = $response['status'];

        if ($status >= 400 || ($body['success'] ?? null) !== true) {
            $code = (string) ($body['code'] ?? $status);
            $message = (string) ($body['message'] ?? 'SlipOK verification failed.');
            throw new VerificationException(
                "SlipOK error {$code}: {$message}",
                provider: self::PROVIDER,
                httpStatus: $status,
                response: $body,
            );
        }

        $data = $body['data'] ?? [];
        if (! is_array($data) || ! isset($data['transRef'])) {
            throw new VerificationException(
                'SlipOK response did not contain a valid data envelope.',
                provider: self::PROVIDER,
                httpStatus: $status,
                response: $body,
            );
        }

        return $this->normalize($data);
    }

    /** @param array<string, mixed> $data */
    private function normalize(array $data): SlipVerification
    {
        $paidAt = isset($data['transTimestamp']) && is_string($data['transTimestamp'])
            ? $this->parseDate($data['transTimestamp'])
            : $this->parseDate(($data['transDate'] ?? '') . 'T' . ($data['transTime'] ?? '00:00:00') . '+07:00');

        return new SlipVerification(
            provider: self::PROVIDER,
            transRef: (string) $data['transRef'],
            paidAt: $paidAt,
            amount: (float) ($data['amount'] ?? 0),
            currency: $data['paidLocalCurrency'] ?? 'THB',
            sender: $this->normalizeParty($data['sender'] ?? []),
            receiver: $this->normalizeParty($data['receiver'] ?? []),
            sendingBankCode: isset($data['sendingBank']) ? (string) $data['sendingBank'] : null,
            receivingBankCode: isset($data['receivingBank']) ? (string) $data['receivingBank'] : null,
            ref1: $this->emptyToNull($data['ref1'] ?? null),
            ref2: $this->emptyToNull($data['ref2'] ?? null),
            ref3: $this->emptyToNull($data['ref3'] ?? null),
            fee: isset($data['transFeeAmount']) ? (float) $data['transFeeAmount'] : null,
            raw: $data,
        );
    }

    /** @param array<string, mixed>|string $party */
    private function normalizeParty(array|string $party): Party
    {
        if (! is_array($party)) {
            return new Party();
        }

        return new Party(
            name: $this->emptyToNull($party['displayName'] ?? $party['name'] ?? null),
            nameTh: $this->emptyToNull($party['displayName'] ?? null),
            nameEn: $this->emptyToNull($party['name'] ?? null),
            accountNumber: $this->emptyToNull($party['account']['value'] ?? null),
            accountType: $this->emptyToNull($party['account']['type'] ?? null),
            proxyType: $this->emptyToNull($party['proxy']['type'] ?? null),
            proxyValue: $this->emptyToNull($party['proxy']['value'] ?? null),
        );
    }

    private function emptyToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = (string) $value;

        return $string === '' ? null : $string;
    }
}
