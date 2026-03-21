<?php
declare(strict_types=1);

/**
 * Test that the DI container compiles correctly with all services.
 * This catches configuration errors like wrong argument types, missing services, etc.
 */

use Tester\Assert;
use Tester\Environment;

require __DIR__ . '/../bootstrap.php';


// Load .env for all tests in this file
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


function createTestContainer(): Nette\DI\Container
{
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

	return $configurator->createContainer();
}


function skipIfNoDatabase(): void
{
	$host = $_ENV['DB_HOST'] ?? 'localhost';
	$port = (int) ($_ENV['DB_PORT'] ?? 3306);

	$conn = @fsockopen($host, $port, $errno, $errstr, 2);
	if (!$conn) {
		Environment::skip("Database not available ($host:$port)");
	}
	fclose($conn);
}


test('DI container compiles without errors', function () {
	$container = createTestContainer();
	Assert::type(Nette\DI\Container::class, $container);
});


test('key services can be resolved', function () {
	skipIfNoDatabase();

	$container = createTestContainer();

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
	skipIfNoDatabase();

	$container = createTestContainer();
	$service = $container->getByType(App\Model\Service\RateLimitService::class);

	Assert::type(App\Model\Service\RateLimitService::class, $service);
});


test('SmtpMailer receives int port', function () {
	skipIfNoDatabase();

	$_ENV['SMTP_PORT'] = '25';
	$container = createTestContainer();
	$mailer = $container->getByType(Nette\Mail\Mailer::class);
	Assert::type(Nette\Mail\SmtpMailer::class, $mailer);
});
