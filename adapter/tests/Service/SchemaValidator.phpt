<?php
declare(strict_types=1);

use App\Model\Service\SchemaValidator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


test('validates valid create_task input', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'title' => 'Test task',
		'project' => 'My Project',
	], 'create-task.input.json');

	Assert::null($errors);
});


test('validates create_task with all optional fields', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'title' => 'Test task',
		'project' => 'My Project',
		'assignee' => 'John',
		'due_date' => '2026-12-31',
		'estimate_hours' => 8,
	], 'create-task.input.json');

	Assert::null($errors);
});


test('rejects create_task without required title', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'project' => 'My Project',
	], 'create-task.input.json');

	Assert::notNull($errors);
	Assert::true(count($errors) > 0);
});


test('rejects create_task without required project', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'title' => 'Test task',
	], 'create-task.input.json');

	Assert::notNull($errors);
});


test('rejects create_task with additional properties', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'title' => 'Test task',
		'project' => 'My Project',
		'unknown_field' => 'value',
	], 'create-task.input.json');

	Assert::notNull($errors);
});


test('validates valid get_job_status input', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'job_id' => '550e8400-e29b-41d4-a716-446655440000',
	], 'get-job-status.input.json');

	Assert::null($errors);
});


test('rejects get_job_status without job_id', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([], 'get-job-status.input.json');

	Assert::notNull($errors);
});


test('validates valid list_my_recent_jobs input', function () {
	$validator = new SchemaValidator();

	// Empty is valid (all optional)
	$errors = $validator->validate([], 'list-my-recent-jobs.input.json');
	Assert::null($errors);

	// With all fields
	$errors = $validator->validate([
		'limit' => 20,
		'status' => 'pending',
		'tool_name' => 'create_task',
	], 'list-my-recent-jobs.input.json');
	Assert::null($errors);
});


test('rejects list_my_recent_jobs with limit over 50', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'limit' => 100,
	], 'list-my-recent-jobs.input.json');

	Assert::notNull($errors);
});


test('rejects list_my_recent_jobs with invalid status', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'status' => 'invalid_status',
	], 'list-my-recent-jobs.input.json');

	Assert::notNull($errors);
});


test('returns error for missing schema file', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate(['foo' => 'bar'], 'nonexistent.json');

	Assert::notNull($errors);
	Assert::true(count($errors) > 0);
});
