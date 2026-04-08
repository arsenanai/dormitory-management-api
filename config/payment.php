<?php

return [
    'default_gateway' => env('PAYMENT_GATEWAY', 'bank_check'),
    'gateways' => [
        'bank_check' => [
            'name' => 'Bank Check (Manual)',
        ],
        // 'kaspi' => [
        //     'merchant_id' => env('KASPI_MERCHANT_ID'),
        //     'api_key'     => env('KASPI_API_KEY'),
        //     'callback_url'=> env('KASPI_CALLBACK_URL'),
        // ],
    ],
];
