<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip\Verify;

use Awcode\ThaiPromptpay\Slip\Verify\Exceptions\VerificationException;
use Awcode\ThaiPromptpay\Slip\Verify\Transport\Transport;

/**
 * EasySlip aggregator (easyslip.com), API v2.
 *
 * Endpoint: POST https://api.easyslip.com/v2/verify/bank
 * Auth:     Authorization: Bearer <apiKey>
 *
 * The slip data lives at data.rawSlip.* (v2 wraps the raw slip with order-
 * matching metadata). Duplicate detection in v2 is signalled via
 * data.isDuplicate on a successful response, not an error code.
 *
 * @see https://document.easyslip.com/en/v2/verify/bank/
 */
final class EasySlipVerifier extends AbstractVerifier
{
    public const PROVIDER = 'easyslip';
    private const ENDPOINT = 'https://api.easyslip.com/v2/verify/bank';

    public function __construct(
        private readonly string $apiKey,
        ?Transport $transport = null,
        private readonly bool $checkDuplicate = false,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('EasySlip requires an apiKey.');
        }
        parent::__construct($transport);
    }

    public function verify(string $input): SlipVerification
    {
        $payload = $this->resolvePayload($input);

        $body = ['payload' => $payload];
        if ($this->checkDuplicate) {
            $body['checkDuplicate'] = true;
        }

        $response = $this->transport->postJson(
            self::ENDPOINT,
            ['Authorization' => 'Bearer ' . $this->apiKey],
            $body,
        );

        $responseBody = $response['body'];
        $status = $response['status'];

        if ($status >= 400 || ($responseBody['success'] ?? null) !== true) {
            $error = is_array($responseBody['error'] ?? null) ? $responseBody['error'] : [];
            $code = (string) ($error['code'] ?? $status);
            $message = (string) ($error['message'] ?? 'EasySlip verification failed.');
            throw new VerificationException(
                "EasySlip error {$code}: {$message}",
                provider: self::PROVIDER,
                httpStatus: $status,
                response: $responseBody,
            );
        }

        $data = $responseBody['data'] ?? [];
        $rawSlip = $data['rawSlip'] ?? null;

        if (! is_array($rawSlip) || ! isset($rawSlip['transRef'])) {
            throw new VerificationException(
                'EasySlip response did not contain data.rawSlip.',
                provider: self::PROVIDER,
                httpStatus: $status,
                response: $responseBody,
            );
        }

        return $this->normalize($rawSlip, $data);
    }

    /**
     * @param  array<string, mixed>  $rawSlip
     * @param  array<string, mixed>  $envelope  v2 outer data block (isDuplicate, matchedAccount, …)
     */
    private function normalize(array $rawSlip, array $envelope): SlipVerification
    {
        $amountValue = $rawSlip['amount']['amount'] ?? 0;
        $localCurrency = $rawSlip['amount']['local']['currency'] ?? '';
        $currency = ($localCurrency !== '' && $localCurrency !== null) ? (string) $localCurrency : 'THB';

        return new SlipVerification(
            provider: self::PROVIDER,
            transRef: (string) $rawSlip['transRef'],
            paidAt: $this->parseDate((string) ($rawSlip['date'] ?? '')),
            amount: (float) $amountValue,
            currency: $currency,
            sender: $this->normalizeParty($rawSlip['sender'] ?? []),
            receiver: $this->normalizeParty($rawSlip['receiver'] ?? []),
            sendingBankCode: $this->stringOrNull($rawSlip['sender']['bank']['id'] ?? null),
            receivingBankCode: $this->stringOrNull($rawSlip['receiver']['bank']['id'] ?? null),
            ref1: $this->stringOrNull($rawSlip['ref1'] ?? null),
            ref2: $this->stringOrNull($rawSlip['ref2'] ?? null),
            ref3: $this->stringOrNull($rawSlip['ref3'] ?? null),
            fee: isset($rawSlip['fee']) ? (float) $rawSlip['fee'] : null,
            raw: $envelope,
        );
    }

    /** @param array<string, mixed>|string $party */
    private function normalizeParty(array|string $party): Party
    {
        if (! is_array($party)) {
            return new Party();
        }

        $bank = is_array($party['bank'] ?? null) ? $party['bank'] : [];
        $accountNameTh = $this->stringOrNull($party['account']['name']['th'] ?? null);
        $accountNameEn = $this->stringOrNull($party['account']['name']['en'] ?? null);

        return new Party(
            name: $accountNameTh ?? $accountNameEn,
            nameTh: $accountNameTh,
            nameEn: $accountNameEn,
            bankCode: $this->stringOrNull($bank['id'] ?? null),
            bankShort: $this->stringOrNull($bank['short'] ?? null),
            bankName: $this->stringOrNull($bank['name'] ?? null),
            accountNumber: $this->stringOrNull($party['account']['bank']['account'] ?? null),
            accountType: $this->stringOrNull($party['account']['bank']['type'] ?? null),
            proxyType: $this->stringOrNull($party['account']['proxy']['type'] ?? null),
            proxyValue: $this->stringOrNull($party['account']['proxy']['account'] ?? null),
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $string = (string) $value;

        return $string === '' ? null : $string;
    }
}
