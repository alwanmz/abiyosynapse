<?php

return [
    'attachments' => [
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
        'max_kb' => 30 * 1024,
    ],

    'portal' => [
        // Default monthly allowance for clients without their own setting.
        'monthly_request_quota' => (int) env('PORTAL_MONTHLY_REQUEST_QUOTA', 10),
        // Ticket types that consume the quota; bug reports stay free.
        'quota_types' => ['feature'],
    ],
];
