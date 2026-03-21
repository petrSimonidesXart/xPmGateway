<?php
declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;

class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator;

        // Load .env file
        $envFile = __DIR__ . '/../.env';
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) {
                    continue;
                }
                if (str_contains($line, '=')) {
                    putenv(trim($line));
                    [$key, $value] = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }

        $configurator->setDebugMode(
            php_sapi_name() === 'cli'
            || ($_ENV['APP_DEBUG'] ?? '') === '1'
            || ($_ENV['APP_ENV'] ?? '') === 'development'
            || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true),
        );
        $configurator->enableTracy(__DIR__ . '/../storage/log');
        $configurator->setTempDirectory(__DIR__ . '/../storage/temp');

        $configurator->addDynamicParameters([
            'env' => $_ENV,
        ]);

        $configurator->addConfig(__DIR__ . '/../config/common.neon');
        $configurator->addConfig(__DIR__ . '/../config/services.neon');

        $localConfig = __DIR__ . '/../config/local.neon';
        if (is_file($localConfig)) {
            $configurator->addConfig($localConfig);
        }

        return $configurator;
    }
}
