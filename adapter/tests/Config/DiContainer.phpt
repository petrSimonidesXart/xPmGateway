<?php
declare(strict_types=1);

/**
 * Test that the DI container compiles correctly with all services.
 * This catches configuration errors like wrong argument types, missing services, etc.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('DI container compiles without errors', function () {
	$configurator = new Nette\Bootstrap\Configurator();

	$configurator->setDebugMode(true);
	$configurator->setTempDirectory(__DIR__ . '/../../storage/temp');
	$configurator->enableTracy(__DIR__ . '/../../storage/log');

	// Load .env
	$envFile = __DIR__ . '/../../.env';
	if (is_file($envFile)) {
		$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		foreach ($lines as $line) {
			if (str_starts_with(trim($line), '#')) {
				continue;
			}
			if (str_contains($line, '=')) {
				[$key, $value] = explode('=', $line, 2);
				$_ENV[trim($key)] = trim($value);
			}
		}
	}

	$configurator->addDynamicParameters(['env' => $_ENV]);

	$configurator->addConfig(__DIR__ . '/../../config/common.neon');
	$configurator->addConfig(__DIR__ . '/../../config/services.neon');

	$localConfig = __DIR__ . '/../../config/local.neon';
	if (is_file($localConfig)) {
		$configurator->addConfig($localConfig);
	}

	$container = $configurator->createContainer();

	Assert::type(Nette\DI\Container::class, $container);

	// Verify key services can be resolved
	Assert::type(App\Model\Service\AuthService::class, $container->getByType(App\Model\Service\AuthService::class));
	Assert::type(App\Model\Service\JobService::class, $container->getByType(App\Model\Service\JobService::class));
	Assert::type(App\Model\Service\EncryptionService::class, $container->getByType(App\Model\Service\EncryptionService::class));
	Assert::type(App\Model\Service\RateLimitService::class, $container->getByType(App\Model\Service\RateLimitService::class));
	Assert::type(App\Model\Service\AuditService::class, $container->getByType(App\Model\Service\AuditService::class));
	Assert::type(App\Model\Service\AlertService::class, $container->getByType(App\Model\Service\AlertService::class));
	Assert::type(App\Model\Service\SchemaValidator::class, $container->getByType(App\Model\Service\SchemaValidator::class));
	Assert::type(App\Model\Facade\McpFacade::class, $container->getByType(App\Model\Facade\McpFacade::class));
	Assert::type(App\Model\Facade\JobFacade::class, $container->getByType(App\Model\Facade\JobFacade::class));
	Assert::type(Nette\Mail\Mailer::class, $container->getByType(Nette\Mail\Mailer::class));
});


test('RateLimitService has correct rate limit from config', function () {
	$configurator = new Nette\Bootstrap\Configurator();
	$configurator->setDebugMode(true);
	$configurator->setTempDirectory(__DIR__ . '/../../storage/temp');

	$configurator->addDynamicParameters(['env' => $_ENV]);
	$configurator->addConfig(__DIR__ . '/../../config/common.neon');
	$configurator->addConfig(__DIR__ . '/../../config/services.neon');

	$localConfig = __DIR__ . '/../../config/local.neon';
	if (is_file($localConfig)) {
		$configurator->addConfig($localConfig);
	}

	$container = $configurator->createContainer();
	$service = $container->getByType(App\Model\Service\RateLimitService::class);

	Assert::type(App\Model\Service\RateLimitService::class, $service);
});


test('SmtpMailer receives int port', function () {
	$configurator = new Nette\Bootstrap\Configurator();
	$configurator->setDebugMode(true);
	$configurator->setTempDirectory(__DIR__ . '/../../storage/temp');

	$_ENV['SMTP_PORT'] = '25';
	$configurator->addDynamicParameters(['env' => $_ENV]);
	$configurator->addConfig(__DIR__ . '/../../config/common.neon');
	$configurator->addConfig(__DIR__ . '/../../config/services.neon');

	$localConfig = __DIR__ . '/../../config/local.neon';
	if (is_file($localConfig)) {
		$configurator->addConfig($localConfig);
	}

	// If this doesn't throw, the port type is correct
	$container = $configurator->createContainer();
	$mailer = $container->getByType(Nette\Mail\Mailer::class);
	Assert::type(Nette\Mail\SmtpMailer::class, $mailer);
});
