<?php

declare(strict_types=1);

return [
    'gateway' => env('PAYMENT_GATEWAY', 'fake'),

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'invoice' => [
        'due_days' => (int) env('INVOICE_DUE_DAYS', 10),
    ],

    'dunning' => [
        'max_retries' => (int) env('DUNNING_MAX_RETRIES', 3),
        'retry_intervals' => [1, 3, 7],
        'suspend_after_days' => (int) env('DUNNING_SUSPEND_AFTER_DAYS', 15),
        'cancel_after_days' => (int) env('DUNNING_CANCEL_AFTER_DAYS', 30),
    ],
];
