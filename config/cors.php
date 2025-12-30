<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    // ... other settings
    'allowed_origins' => env('APP_ENV') === 'local'
        ? ['*'] // Allow all origins in development
        : [
            env('SPA_URL'), // Will be 'https://dorm.sdu.edu.kz'
            // Keep other necessary domains if applicable, or remove them:
            // env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    // Remove the allowed_origins_patterns section entirely or set it to []
    'allowed_origins_patterns' => [],
    // ... other settings
];
