<?php

return [
    'enabled' => env('APP_UPDATE_ENABLED', true),
    'repository' => env('APP_UPDATE_REPOSITORY', 'ytsuyuzaki/cuckooremind'),
    'api_url' => env('APP_UPDATE_API_URL', 'https://api.github.com'),
    'github_token' => env('APP_UPDATE_GITHUB_TOKEN'),
    'cache_ttl' => (int) env('APP_UPDATE_CACHE_TTL', 21600),
    'timeout' => (int) env('APP_UPDATE_TIMEOUT', 15),
    'maximum_download_size' => (int) env('APP_UPDATE_MAX_SIZE', 150 * 1024 * 1024),
    'backup_keep' => (int) env('APP_UPDATE_BACKUP_KEEP', 3),
    'allowed_download_hosts' => [
        'github.com',
        'objects.githubusercontent.com',
        'release-assets.githubusercontent.com',
    ],
];
