<?php

declare(strict_types=1);

return [
    'enabled' => env('SEVIAFLOW_UI_ENABLED', true),
    'assets' => [
        'enabled' => true,
        'path' => 'vendor/seviaflow/ui',
    ],
    'route_prefix' => 'admin',
    'route_name_prefix' => 'seviaflow.',
    'admin_middleware' => ['web', 'auth'],
    'middleware' => ['web', 'auth'],
    'auth_middleware' => ['web'],
    'auth' => [
        'enabled' => true,
        'guard' => 'web',
        'password_broker' => 'users',
        'two_factor_authentication' => true,
        'redirect_after_login' => null,
        'redirect_after_logout' => null,
    ],
    'dashboard' => [
        'enabled' => true,
        'default' => null,
        'show_resource_counts' => false,
        'show_workflow_attention_counts' => false,
        'recent_activity_limit' => 10,
        'cache_ttl' => 60,
        'table_widgets' => [
            'max_limit' => 25,
        ],
        'workflow_widgets' => [
            'max_limit' => 25,
        ],
        'recent_activity_widgets' => [
            'max_limit' => 25,
        ],
    ],
    'cache' => [
        'navigation' => true,
        'dashboard' => false,
    ],
    'dashboards' => [],
    'bulk_actions' => [
        'max_selected' => 100,
    ],
    'api_docs_navigation' => [
        'show_in_navigation' => true,
        'route_name' => 'seviaflow.api.docs',
        'permission' => [
            'resource' => '__settings.api-docs',
            'ability' => 'apiDocs.view',
        ],
    ],
];
