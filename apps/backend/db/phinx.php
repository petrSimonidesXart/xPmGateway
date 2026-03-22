<?php

declare(strict_types=1);

// Load .env from backend root
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            putenv(trim($line));
        }
    }
}

$host = getenv('DB_HOST');
$name = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');

if (!$host || !$name || !$user) {
    throw new RuntimeException('Missing required DB_HOST, DB_NAME, or DB_USER env variables. Copy .env.example to .env and configure.');
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinx_log',
        'default_environment' => 'production',
        'production' => [
            'adapter' => 'mysql',
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass ?: '',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
    'version_order' => 'creation',
];
