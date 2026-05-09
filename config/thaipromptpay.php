<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default merchant identity (optional)
    |--------------------------------------------------------------------------
    |
    | If your application typically issues PromptPay QRs to a single account,
    | set the relevant value here and read it from your code rather than
    | hard-coding it. Leaving any of these as null is fine — the package does
    | not consume them itself.
    |
    */

    'phone' => env('PROMPTPAY_PHONE'),
    'national_id' => env('PROMPTPAY_NATIONAL_ID'),
    'ewallet' => env('PROMPTPAY_EWALLET'),
    'biller_id' => env('PROMPTPAY_BILLER_ID'),

    /*
    |--------------------------------------------------------------------------
    | Default QR rendering options
    |--------------------------------------------------------------------------
    */

    'qr' => [
        'size' => 300,
        'margin' => 1,
    ],
];
