<?php
declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;

class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator();

        $configurator->setDebugMode(
            php_sapi_name() === 'cli'
            || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
        );
        $configurator->enableTracy(__DIR__ . '/../storage/log');
        $configurator->setTempDirectory(__DIR__ . '/../storage/temp');

        $configurator->addConfig(__DIR__ . '/../config/common.neon');
        $configurator->addConfig(__DIR__ . '/../config/services.neon');

        $localConfig = __DIR__ . '/../config/local.neon';
        if (is_file($localConfig)) {
            $configurator->addConfig($localConfig);
        }

        return $configurator;
    }
}
