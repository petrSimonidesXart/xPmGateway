<?php
declare(strict_types=1);

/**
 * Test that all Latte templates compile without errors.
 * Catches syntax issues like ternary+filter conflicts.
 */

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('all Admin templates compile without errors', function () {
	$engine = new Latte\Engine();
	$engine->setTempDirectory(__DIR__ . '/../../storage/temp');
	$engine->addExtension(new Nette\Bridges\ApplicationLatte\UIExtension(null));
	$engine->addExtension(new Nette\Bridges\FormsLatte\FormsExtension());

	$templateDir = __DIR__ . '/../../app/Module/Admin/templates';
	$files = glob($templateDir . '/{,*/}*.latte', GLOB_BRACE);

	Assert::true(count($files) > 0, 'No template files found');

	$errors = [];
	foreach ($files as $file) {
		try {
			$engine->compile($file);
		} catch (\Throwable $e) {
			$relative = str_replace($templateDir . '/', '', $file);
			$errors[] = "{$relative}: {$e->getMessage()}";
		}
	}

	if ($errors !== []) {
		Assert::fail("Template compilation errors:\n" . implode("\n", $errors));
	}
});
