<?php

return [
    'enabled' => env('CHAT_ENABLED', true),

    'database' => [
        'connection' => env('CHAT_DB_CONNECTION', 'chat'),
        'migrations' => env('CHAT_MIGRATIONS_PATH', 'database/migrations'),
        'table_prefix' => env('CHAT_TABLE_PREFIX', 'chat_'),
    ],

    'auth' => [
        'mode' => env('CHAT_AUTH_MODE', 'auto'),
        'guard' => env('CHAT_AUTH_GUARD', 'web'),
        'resolver' => env('CHAT_AUTH_RESOLVER', Vendor\LaravelChat\Support\DefaultChatUserResolver::class),
        'user_model' => env('CHAT_USER_MODEL', config('auth.providers.users.model', App\Models\User::class)),
    ],

    'routes' => [
        'prefix' => env('CHAT_ROUTE_PREFIX', 'messages'),
        'middleware' => array_filter(array_map('trim', explode(',', env('CHAT_ROUTE_MIDDLEWARE', 'web,auth')))),
    ],

    'storage' => [
        'disk' => env('CHAT_STORAGE_DISK', 'local'),
        'max_size_kb' => (int) env('CHAT_STORAGE_MAX_SIZE_KB', 10240),
    ],

    'notifications' => [
        'enabled' => env('CHAT_NOTIFICATIONS_ENABLED', true),
        'driver' => env('CHAT_NOTIFICATIONS_DRIVER', 'database'),
    ],

    'realtime' => [
        'enabled' => env('CHAT_REALTIME_ENABLED', true),
        'driver' => env('CHAT_REALTIME_DRIVER', 'auto'),
    ],
];
