<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Mail event → mailable mapping
    |--------------------------------------------------------------------------
    | Add new mail events by registering a mailable class and recipient resolver.
    | Recipient resolver: 'user' | 'payment_user' | 'message_recipients'
    */

    'user.registered' => [
        'mailable' => \App\Mail\UserRegisteredMail::class,
        'recipient' => 'user',
    ],

    'payment.status_changed' => [
        'mailable' => \App\Mail\PaymentStatusChangedMail::class,
        'recipient' => 'payment_user',
    ],

    'user.status_changed' => [
        'mailable' => \App\Mail\UserStatusChangedMail::class,
        'recipient' => 'user',
    ],

    'message.sent' => [
        'mailable' => \App\Mail\MessageSentMail::class,
        'recipient' => 'message_recipients',
    ],
];
