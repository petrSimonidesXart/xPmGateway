<?php
declare(strict_types=1);

use App\Model\Service\SchemaValidator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('valid data against a raw schema returns null', function () {
	$validator = new SchemaValidator();

	$schema = [
		'type' => 'object',
		'properties' => [
			'name' => ['type' => 'string'],
			'age' => ['type' => 'integer'],
		],
		'required' => ['name'],
	];

	$errors = $validator->validateRaw([
		'name' => 'Petr',
		'age' => 30,
	], $schema);

	Assert::null($errors);
});


test('missing required field returns error array', function () {
	$validator = new SchemaValidator();

	$schema = [
		'type' => 'object',
		'properties' => [
			'name' => ['type' => 'string'],
			'email' => ['type' => 'string'],
		],
		'required' => ['name', 'email'],
	];

	$errors = $validator->validateRaw([
		'name' => 'Petr',
	], $schema);

	Assert::notNull($errors);
	Assert::true(count($errors) > 0);
});


test('wrong type returns error array', function () {
	$validator = new SchemaValidator();

	$schema = [
		'type' => 'object',
		'properties' => [
			'age' => ['type' => 'integer'],
		],
		'required' => ['age'],
	];

	$errors = $validator->validateRaw([
		'age' => 'not-a-number',
	], $schema);

	Assert::notNull($errors);
	Assert::true(count($errors) > 0);
});


test('schema with no required fields passes any data', function () {
	$validator = new SchemaValidator();

	$schema = ['type' => 'object', 'properties' => new \stdClass];

	$errors = $validator->validateRaw([
		'anything' => 'goes',
		'nested' => ['data' => 123],
	], $schema);

	Assert::null($errors);
});


test('minLength validation works', function () {
	$validator = new SchemaValidator();

	$schema = [
		'type' => 'object',
		'properties' => [
			'username' => [
				'type' => 'string',
				'minLength' => 3,
			],
		],
		'required' => ['username'],
	];

	// Valid: meets minLength
	$errors = $validator->validateRaw([
		'username' => 'abc',
	], $schema);

	Assert::null($errors);

	// Invalid: too short
	$errors = $validator->validateRaw([
		'username' => 'ab',
	], $schema);

	Assert::notNull($errors);
	Assert::true(count($errors) > 0);
});
