<?php

return [
    'enabled' => env('SWAGGER_DOCS_ENABLED', true),

    'title' => env('SWAGGER_TITLE', 'Jocrams API'),
    'description' => env('SWAGGER_DESCRIPTION', 'Interactive OpenAPI documentation for the Jocrams API.'),
    'version' => env('SWAGGER_VERSION', '1.0.0'),

    'docs_path' => env('SWAGGER_DOCS_PATH', 'docs'),
    'json_path' => env('SWAGGER_OPENAPI_PATH', 'docs/openapi.json'),

    'servers' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('SWAGGER_SERVERS', env('APP_URL', 'http://localhost')))
    ))),

    'include_paths' => [
        'api/v1/*',
    ],
];
