<?php

return [
    'default_gateway' => env('PAYMENT_DEFAULT_GATEWAY', 'paystack'),

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
    ],

    'flutterwave' => [
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
        'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
        'base_url' => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com/v3'),
        'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
    ],

    'callback_url' => env('PAYMENT_CALLBACK_URL', env('APP_URL').'/payments/return'),
    'currency' => env('PAYMENT_CURRENCY', 'NGN'),
];
