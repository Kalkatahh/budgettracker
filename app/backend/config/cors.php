<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'], // Define routes where CORS applies

    'allowed_methods' => ['*'], // Allow all HTTP methods

    'allowed_origins' => ['http://localhost:5173'], // Replace with your frontend's URL

    'allowed_origins_patterns' => [], // Use patterns if needed

    'allowed_headers' => ['*'], // Allow all headers

    'exposed_headers' => [], // Headers exposed to the browser

    'max_age' => 0, // Cache duration for preflight requests

    'supports_credentials' => true, // Allow cookies or authentication headers
];
