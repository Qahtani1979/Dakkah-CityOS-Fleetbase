<?php

return [
    'api' => [
        'routing' => [
            'prefix' => 'cityos',
            'internal_prefix' => 'int',
        ],
    ],
    'node_context' => [
        'header_prefix' => 'X-CityOS-',
        'cookie_prefix' => 'cityos_',
        'required_fields' => ['country', 'tenant'],
        'default_locale' => 'ar-SA',
        'default_processing_region' => 'me-central-1',
        'default_residency_class' => 'sovereign',
    ],
];
