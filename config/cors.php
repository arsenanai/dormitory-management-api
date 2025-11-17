<?php

return [ 
	'paths'                    => [ 'api/*', 'sanctum/csrf-cookie' ],
	'allowed_methods'          => [ '*' ],
	'allowed_origins' => env('APP_ENV') === 'local'
		? ['*'] // Allow all origins in development
		: [
			env('SPA_URL', 'http://localhost:3000'),
			env('FRONTEND_URL', 'http://localhost:3000'),
			'http://localhost:3000',
			'http://127.0.0.1:3000',
			'https://dorm.sdu.edu.kz',
    ],
	'allowed_origins_patterns' => env('APP_ENV') === 'local'
		? ['*'] // Allow all patterns in development
		: ['https://.*.sdu.edu.kz'],
	'allowed_headers'          => [ '*' ],
	'exposed_headers'          => [],
	'max_age'                  => 0,
	'supports_credentials'     => true,
];