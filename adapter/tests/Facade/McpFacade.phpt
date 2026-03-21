<?php
declare(strict_types=1);

use App\Model\Facade\McpException;
use App\Model\Facade\McpFacade;
use App\Model\Repository\ToolRepository;
use App\Model\Service\ArtifactService;
use App\Model\Service\AuthService;
use App\Model\Service\AuditService;
use App\Model\Service\JobService;
use App\Model\Service\RateLimitService;
use App\Model\Service\SchemaValidator;
use Nette\Database\Table\ActiveRow;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


/**
 * Mock ActiveRow — extends real ActiveRow, overrides accessColumn() to avoid
 * needing a Selection/database connection. Supports ref() via manual mapping.
 */
class MockActiveRow extends ActiveRow
{
	private array $refs = [];


	public function __construct(array $data = [])
	{
		// Skip parent constructor — set private properties via reflection
		$ref = new ReflectionClass(ActiveRow::class);

		$dataProp = $ref->getProperty('data');
		$dataProp->setValue($this, $data);

		$refreshProp = $ref->getProperty('dataRefreshed');
		$refreshProp->setValue($this, true);
	}


	public function accessColumn(?string $key, bool $selectColumn = true): bool
	{
		// no-op — skip table/database access
		return true;
	}


	public function addRef(string $key, string $throughColumn, ?ActiveRow $target): self
	{
		$this->refs["$key:$throughColumn"] = $target;
		return $this;
	}


	public function ref(string $key, ?string $throughColumn = null): ?ActiveRow
	{
		return $this->refs["$key:$throughColumn"] ?? null;
	}
}


function mockRow(array $data = []): MockActiveRow
{
	return new MockActiveRow($data);
}


// --- Mock subclasses extending real types ---

class TestAuthService extends AuthService
{
	public bool $hasPermission = true;
	public bool $ipAllowed = true;


	public function __construct()
	{
	}


	public function hasToolPermission(int $clientId, int $toolId): bool
	{
		return $this->hasPermission;
	}


	public function isIpAllowed(object $client, string $ip): bool
	{
		return $this->ipAllowed;
	}
}


class TestJobService extends JobService
{
	public ?ActiveRow $createdJob = null;
	public ?ActiveRow $completedJob = null;
	public ?ActiveRow $foundJob = null;
	public array $clientJobs = [];


	public function __construct()
	{
	}


	public function createJob(int $clientId, int $serviceAccountId, int $toolId, array $payload): ActiveRow
	{
		return $this->createdJob ?? mockRow(['id' => 'job-uuid-1']);
	}


	public function waitForCompletion(string $jobId, int $timeoutSeconds = 20): ?ActiveRow
	{
		return $this->completedJob;
	}


	public function findById(string $jobId): ?ActiveRow
	{
		return $this->foundJob;
	}


	public function getClientJobs(int $clientId, ?string $status = null, ?string $toolName = null, int $limit = 10): array
	{
		return $this->clientJobs;
	}
}


class TestAuditService extends AuditService
{
	public int $callCount = 0;


	public function __construct()
	{
	}


	public function logMcpCall(
		?ActiveRow $client,
		?ActiveRow $token,
		string $toolName,
		string $resultStatus,
		?array $payload = null,
		?array $resultData = null,
		?string $jobId = null,
		?int $durationMs = null,
	): void {
		$this->callCount++;
	}


	public function logSecurityEvent(
		string $action,
		string $resultStatus,
		?ActiveRow $client = null,
		?array $payload = null,
	): void {
		$this->callCount++;
	}
}


class TestRateLimitService extends RateLimitService
{
	public int $remaining = 10;


	public function __construct()
	{
	}


	public function checkTokenLimit(int $tokenId): int
	{
		return $this->remaining;
	}
}


class TestSchemaValidator extends SchemaValidator
{
	public ?array $errors = null;


	public function __construct()
	{
	}


	public function validate(array $data, string $schemaFile): ?array
	{
		return $this->errors;
	}
}


class TestToolRepository extends ToolRepository
{
	public ?ActiveRow $tool = null;


	public function __construct()
	{
	}


	public function findByName(string $name): ?ActiveRow
	{
		return $this->tool;
	}
}


class TestArtifactService extends ArtifactService
{
	public array $artifacts = [];


	public function __construct()
	{
	}


	public function findByJobId(string $jobId): array
	{
		return $this->artifacts;
	}


	public function formatForResponse(array $artifacts, string $baseUrl = ''): array
	{
		return array_map(fn($a) => ['id' => $a->id], $artifacts);
	}
}


// --- Factory ---

function createFacade(array $overrides = []): array
{
	$auth = new TestAuthService();
	$job = new TestJobService();
	$audit = new TestAuditService();
	$rateLimit = new TestRateLimitService();
	$schema = new TestSchemaValidator();
	$tools = new TestToolRepository();
	$artifacts = new TestArtifactService();

	foreach ($overrides as $key => $value) {
		match ($key) {
			'rateLimitRemaining' => $rateLimit->remaining = $value,
			'tool' => $tools->tool = $value,
			'hasPermission' => $auth->hasPermission = $value,
			'ipAllowed' => $auth->ipAllowed = $value,
			'validationErrors' => $schema->errors = $value,
			'completedJob' => $job->completedJob = $value,
			'foundJob' => $job->foundJob = $value,
			'clientJobs' => $job->clientJobs = $value,
			'artifacts' => $artifacts->artifacts = $value,
			default => null,
		};
	}

	$ref = new ReflectionClass(McpFacade::class);
	$facade = $ref->newInstanceWithoutConstructor();

	$services = [
		'authService' => $auth,
		'jobService' => $job,
		'auditService' => $audit,
		'rateLimitService' => $rateLimit,
		'schemaValidator' => $schema,
		'toolRepository' => $tools,
		'artifactService' => $artifacts,
	];

	foreach ($services as $name => $service) {
		$prop = $ref->getProperty($name);
		$prop->setValue($facade, $service);
	}

	return [$facade, $audit];
}


// --- Shared fixtures ---

$client = mockRow(['id' => 1, 'name' => 'TestClient', 'service_account_id' => 1]);
$token = mockRow(['id' => 100]);
$activeTool = mockRow(['id' => 5, 'name' => 'create_task', 'is_active' => true]);


// === Error path tests ===

test('rate limit exceeded throws 429', function () use ($client, $token) {
	[$facade] = createFacade(['rateLimitRemaining' => -1]);

	Assert::exception(
		fn() => $facade->handleToolCall($client, $token, 'create_task', [], '127.0.0.1'),
		McpException::class,
		'Rate limit exceeded',
	);
});


test('unknown tool throws 404', function () use ($client, $token) {
	[$facade] = createFacade(['tool' => null]);

	Assert::exception(
		fn() => $facade->handleToolCall($client, $token, 'nonexistent', [], '127.0.0.1'),
		McpException::class,
		'Unknown tool',
	);
});


test('inactive tool throws 404', function () use ($client, $token) {
	[$facade] = createFacade([
		'tool' => mockRow(['id' => 5, 'name' => 'create_task', 'is_active' => false]),
	]);

	Assert::exception(
		fn() => $facade->handleToolCall($client, $token, 'create_task', [], '127.0.0.1'),
		McpException::class,
		'Unknown tool',
	);
});


test('permission denied for non-meta tool throws 403', function () use ($client, $token, $activeTool) {
	[$facade] = createFacade([
		'tool' => $activeTool,
		'hasPermission' => false,
	]);

	Assert::exception(
		fn() => $facade->handleToolCall($client, $token, 'create_task', [], '127.0.0.1'),
		McpException::class,
		'Permission denied for this tool',
	);
});


test('meta-tool bypasses permission check', function () use ($client, $token) {
	$statusTool = mockRow(['id' => 10, 'name' => 'get_job_status', 'is_active' => true]);
	$foundJob = mockRow([
		'id' => 'job-1',
		'status' => 'success',
		'client_id' => 1,
		'result' => '{"task_id":"42"}',
		'error_message' => null,
		'finished_at' => null,
	]);

	[$facade] = createFacade([
		'tool' => $statusTool,
		'hasPermission' => false,
		'foundJob' => $foundJob,
	]);

	$result = $facade->handleToolCall($client, $token, 'get_job_status', ['job_id' => 'job-1'], '127.0.0.1');
	Assert::same('success', $result['status']);
});


test('IP not allowed throws 403', function () use ($client, $token, $activeTool) {
	[$facade] = createFacade([
		'tool' => $activeTool,
		'ipAllowed' => false,
	]);

	Assert::exception(
		fn() => $facade->handleToolCall($client, $token, 'create_task', [], '127.0.0.1'),
		McpException::class,
		'IP not allowed',
	);
});


test('schema validation failure throws 422', function () use ($client, $token, $activeTool) {
	[$facade] = createFacade([
		'tool' => $activeTool,
		'validationErrors' => ['title: field is required'],
	]);

	Assert::exception(
		fn() => $facade->handleToolCall($client, $token, 'create_task', [], '127.0.0.1'),
		McpException::class,
		'Validation failed: title: field is required',
	);
});


// === Happy path tests ===

test('create_task completed within timeout returns mode done', function () use ($client, $token, $activeTool) {
	$completedJob = mockRow([
		'id' => 'job-1',
		'status' => 'success',
		'result' => '{"task_id":"42"}',
	]);

	[$facade] = createFacade([
		'tool' => $activeTool,
		'completedJob' => $completedJob,
	]);

	$result = $facade->handleToolCall($client, $token, 'create_task', ['title' => 'Test', 'project' => 'Proj'], '127.0.0.1');

	Assert::same('done', $result['mode']);
	Assert::same('success', $result['status']);
	Assert::same('42', $result['task_id']);
});


test('create_task not completed returns mode queued', function () use ($client, $token, $activeTool) {
	[$facade] = createFacade([
		'tool' => $activeTool,
		'completedJob' => null,
	]);

	$result = $facade->handleToolCall($client, $token, 'create_task', ['title' => 'Test', 'project' => 'Proj'], '127.0.0.1');

	Assert::same('queued', $result['mode']);
	Assert::same('pending', $result['status']);
	Assert::type('string', $result['job_id']);
});


test('get_job_status returns job data', function () use ($client, $token) {
	$statusTool = mockRow(['id' => 10, 'name' => 'get_job_status', 'is_active' => true]);

	$finishedAt = new \DateTimeImmutable('2026-03-15 10:00:00');
	$foundJob = mockRow([
		'id' => 'job-1',
		'status' => 'success',
		'client_id' => 1,
		'result' => '{"message":"Task created"}',
		'error_message' => null,
		'finished_at' => $finishedAt,
	]);

	[$facade] = createFacade([
		'tool' => $statusTool,
		'foundJob' => $foundJob,
	]);

	$result = $facade->handleToolCall($client, $token, 'get_job_status', ['job_id' => 'job-1'], '127.0.0.1');

	Assert::same('success', $result['status']);
	Assert::same('Task created', $result['result']['message']);
	Assert::same($finishedAt->format('c'), $result['finished_at']);
});


test('audit service is called on every tool call', function () use ($client, $token, $activeTool) {
	[$facade, $audit] = createFacade([
		'tool' => $activeTool,
		'completedJob' => null,
	]);

	$facade->handleToolCall($client, $token, 'create_task', ['title' => 'T', 'project' => 'P'], '127.0.0.1');

	Assert::true($audit->callCount >= 1);
});
