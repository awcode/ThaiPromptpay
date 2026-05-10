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

    /*
    |--------------------------------------------------------------------------
    | Slip verification
    |--------------------------------------------------------------------------
    |
    | Optional. When configured, ThaiPromptpay::verify($input) routes to the
    | named provider. Leave 'default' as null to disable — local parsing
    | (parseSlip / scanSlip) still works without any of this.
    |
    | Each provider here is opt-in: only fill in the credentials for the
    | provider(s) you actually use.
    |
    */

    'verifier' => [

        'default' => env('PROMPTPAY_VERIFIER'),

        'providers' => [

            'slipok' => [
                'api_key' => env('SLIPOK_API_KEY'),
                'branch_id' => env('SLIPOK_BRANCH_ID'),
                'log_slips' => true,
            ],

            'easyslip' => [
                'api_key' => env('EASYSLIP_API_KEY'),
                'check_duplicate' => false,
            ],

        ],
    ],
];
