<?php
declare(strict_types=1);

use App\Model\Service\EncryptionService;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('encrypts and decrypts correctly', function () {
	$service = new EncryptionService('base64:' . base64_encode(random_bytes(32)));
	$plaintext = 'Hello, World! Tajné heslo 123';

	$encrypted = $service->encrypt($plaintext);
	Assert::notSame($plaintext, $encrypted);

	$decrypted = $service->decrypt($encrypted);
	Assert::same($plaintext, $decrypted);
});


test('different encryptions produce different ciphertexts', function () {
	$service = new EncryptionService('base64:' . base64_encode(random_bytes(32)));
	$plaintext = 'same text';

	$enc1 = $service->encrypt($plaintext);
	$enc2 = $service->encrypt($plaintext);
	Assert::notSame($enc1, $enc2); // Different IVs
});


test('decryption with wrong key fails', function () {
	$service1 = new EncryptionService('base64:' . base64_encode(random_bytes(32)));
	$service2 = new EncryptionService('base64:' . base64_encode(random_bytes(32)));

	$encrypted = $service1->encrypt('secret');

	Assert::exception(
		fn() => $service2->decrypt($encrypted),
		Nette\InvalidStateException::class,
	);
});


test('rejects short encryption key', function () {
	Assert::exception(
		fn() => new EncryptionService('short'),
		Nette\InvalidStateException::class,
		'Encryption key must be at least 16 bytes',
	);
});


test('handles raw key without base64 prefix', function () {
	$key = str_repeat('a', 32);
	$service = new EncryptionService($key);

	$encrypted = $service->encrypt('test');
	$decrypted = $service->decrypt($encrypted);
	Assert::same('test', $decrypted);
});


test('rejects invalid encrypted data', function () {
	$service = new EncryptionService('base64:' . base64_encode(random_bytes(32)));

	Assert::exception(
		fn() => $service->decrypt('not-valid-base64!!!'),
		Nette\InvalidStateException::class,
	);

	Assert::exception(
		fn() => $service->decrypt(base64_encode('tooshort')),
		Nette\InvalidStateException::class,
	);
});
