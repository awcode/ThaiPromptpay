<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Laravel\Facades;

use Awcode\ThaiPromptpay\Builder;
use Awcode\ThaiPromptpay\Slip\SlipQr;
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
