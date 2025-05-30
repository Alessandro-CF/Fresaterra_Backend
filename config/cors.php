<?php
return [
    'paths' => ['api/*', 'cart/api/*', 'cart/api/v1/*'],  // Rutas donde aplicar CORS

    'allowed_methods' => ['*'], // Permitir todos los métodos HTTP

    'allowed_origins' => ['http://localhost:5173'], // Origen de tu frontend

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Permitir todos los headers

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
?>