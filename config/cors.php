<?php

return [ 
	'paths'                    => [ 'api/*', 'sanctum/csrf-cookie' ],
	'allowed_methods'          => [ '*' ],
	'allowed_origins'          => [ 
		env( 'SPA_URL', 'http://localhost:3000' ),
		env( 'FRONTEND_URL', 'http://localhost:3000' ),
		'http://localhost:8080',
		'http://localhost:5173',
		'https://dorm.sdu.edu.kz'
	],
	'allowed_origins_patterns' => [ 
		'https://*.sdu.edu.kz'
	],
	'allowed_headers'          => [ '*' ],
	'exposed_headers'          => [],
	'max_age'                  => 0,
	'supports_credentials'     => true,
];