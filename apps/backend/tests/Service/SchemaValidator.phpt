<?php
declare(strict_types=1);

use App\Model\Service\SchemaValidator;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// -- pm_create_comment --

test('validates valid pm_create_comment input', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'text' => 'Tohle je komentář',
	], 'pm-create-comment.input.json');

	Assert::null($errors);
});


test('rejects pm_create_comment without required text', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([], 'pm-create-comment.input.json');

	Assert::notNull($errors);
	Assert::true(count($errors) > 0);
});


test('rejects pm_create_comment with empty text', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'text' => '',
	], 'pm-create-comment.input.json');

	Assert::notNull($errors);
});


test('rejects pm_create_comment with additional properties', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'text' => 'Komentář',
		'unknown_field' => 'value',
	], 'pm-create-comment.input.json');

	Assert::notNull($errors);
});


// -- pm_open_project --

test('validates valid pm_open_project input', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'query' => 'Název projektu',
	], 'pm-open-project.input.json');

	Assert::null($errors);
});


test('rejects pm_open_project without query', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([], 'pm-open-project.input.json');

	Assert::notNull($errors);
});


// -- pm_create_subtask --

test('validates pm_create_subtask with required fields only', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'name' => 'Nový podúkol',
	], 'pm-create-subtask.input.json');

	Assert::null($errors);
});


test('validates pm_create_subtask with all optional fields', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'name' => 'Nový podúkol',
		'assignee' => 'PS',
		'label' => 'RESIT',
		'schedule' => 'this_week',
		'estimate' => '2',
	], 'pm-create-subtask.input.json');

	Assert::null($errors);
});


test('rejects pm_create_subtask without name', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'assignee' => 'PS',
	], 'pm-create-subtask.input.json');

	Assert::notNull($errors);
});


// -- pm_time_track --

test('validates valid pm_time_track input', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'hours' => 2.5,
		'date' => '2026-03-22',
	], 'pm-time-track.input.json');

	Assert::null($errors);
});


test('validates pm_time_track with optional note', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'hours' => 1,
		'date' => '2026-03-22',
		'note' => 'Práce na refaktoru',
	], 'pm-time-track.input.json');

	Assert::null($errors);
});


test('rejects pm_time_track without hours', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'date' => '2026-03-22',
	], 'pm-time-track.input.json');

	Assert::notNull($errors);
});


test('rejects pm_time_track without date', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'hours' => 2,
	], 'pm-time-track.input.json');

	Assert::notNull($errors);
});


// -- pm_export_csv --

test('validates valid pm_export_csv input', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'type' => 'tasks',
	], 'pm-export-csv.input.json');

	Assert::null($errors);
});


test('rejects pm_export_csv with invalid type', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'type' => 'invalid',
	], 'pm-export-csv.input.json');

	Assert::notNull($errors);
});


// -- pm_login --

test('validates pm_login accepts empty input', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([], 'pm-login.input.json');

	Assert::null($errors);
});


test('rejects pm_login with additional properties', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([
		'username' => 'test',
	], 'pm-login.input.json');

	Assert::notNull($errors);
});


// -- get_job_status --

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


// -- list_my_recent_jobs --

test('validates valid list_my_recent_jobs input', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate([], 'list-my-recent-jobs.input.json');
	Assert::null($errors);

	$errors = $validator->validate([
		'limit' => 20,
		'status' => 'pending',
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


// -- edge cases --

test('returns error for missing schema file', function () {
	$validator = new SchemaValidator();

	$errors = $validator->validate(['foo' => 'bar'], 'nonexistent.json');

	Assert::notNull($errors);
	Assert::true(count($errors) > 0);
});
