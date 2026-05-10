<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Laravel\Facades;

use Awcode\ThaiPromptpay\Builder;
use Awcode\ThaiPromptpay\Slip\SlipQr;
use Awcode\ThaiPromptpay\Slip\Verify\EasySlipVerifier;
use Awcode\ThaiPromptpay\Slip\Verify\SlipOkVerifier;
use Awcode\ThaiPromptpay\Slip\Verify\SlipVerification;
use Awcode\ThaiPromptpay\ThaiPromptpay as ThaiPromptpayClass;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Builder phone(string $phone)
 * @method static Builder nationalId(string $id)
 * @method static Builder eWallet(string $id)
 * @method static Builder billPayment(string $billerId)
 * @method static SlipQr parseSlip(string $payload)
 * @method static SlipQr scanSlip(string $image)
 * @method static SlipQr readSlip(string $input)
 * @method static SlipOkVerifier slipOk(string $apiKey, string $branchId)
 * @method static EasySlipVerifier easySlip(string $apiKey)
 * @method static SlipVerification verify(string $input)
 *
 * @see \Awcode\ThaiPromptpay\ThaiPromptpay
 */
class ThaiPromptpay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ThaiPromptpayClass::class;
    }
}
