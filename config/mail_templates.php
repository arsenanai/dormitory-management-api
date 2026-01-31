<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Mail template types (event keys) and their display names
    |--------------------------------------------------------------------------
    */
    'types' => [
        'user_registered'       => 'Registration Complete',
        'payment_status_changed' => 'Payment Status Update',
        'user_status_changed'    => 'Account Status Update',
        'message_sent'          => 'New Message',
    ],

    /*
    |--------------------------------------------------------------------------
    | Available placeholders per template type (for sudo UI)
    | Keys are placeholder names used in templates as {{placeholder_name}}
    | Values are short descriptions for the UI.
    |--------------------------------------------------------------------------
    */
    'placeholders' => [
        'user_registered' => [
            'app_name'     => 'Application name (config)',
            'admin_email'  => 'Dormitory admin email (or system)',
            'user_name'    => 'Recipient full name',
            'user_email'   => 'Recipient email',
            'year'         => 'Current year',
        ],
        'payment_status_changed' => [
            'app_name'          => 'Application name (config)',
            'admin_email'       => 'Dormitory admin email',
            'user_name'         => 'Recipient full name',
            'user_email'        => 'Recipient email',
            'amount_formatted'  => 'Payment amount with currency',
            'deal_number'       => 'Payment deal number',
            'current_status'    => 'Current payment status',
            'period_from'       => 'Period start date',
            'period_to'         => 'Period end date',
            'year'              => 'Current year',
        ],
        'user_status_changed' => [
            'app_name'     => 'Application name (config)',
            'admin_email'  => 'Dormitory admin email',
            'user_name'    => 'Recipient full name',
            'user_email'   => 'Recipient email',
            'current_status' => 'Current account status',
            'year'         => 'Current year',
        ],
        'message_sent' => [
            'app_name'               => 'Application name (config)',
            'admin_email'            => 'Dormitory admin email',
            'user_name'              => 'Recipient full name',
            'user_email'             => 'Recipient email',
            'message_title'          => 'Message title',
            'message_content_preview' => 'Message content (short preview)',
            'year'                   => 'Current year',
        ],
    ],

    'supported_locales' => [ 'en', 'kk', 'ru' ],
];
