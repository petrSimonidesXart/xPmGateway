<?php
declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Repository\ScenarioRepository;
use App\Model\Repository\ToolRepository;
use App\Model\Service\ArtifactService;
use App\Model\Service\AuditService;
use App\Model\Service\AuthService;
use App\Model\Service\JobService;
use App\Model\Service\RateLimitService;
use App\Model\Service\SchemaValidator;
use Nette\Database\Table\ActiveRow;

class McpFacade
{
	public function __construct(
		private AuthService $authService,
		private JobService $jobService,
		private AuditService $auditService,
		private RateLimitService $rateLimitService,
		private SchemaValidator $schemaValidator,
		private ToolRepository $toolRepository,
		private ScenarioRepository $scenarioRepository,
		private ArtifactService $artifactService,
	) {
	}


	/**
	 * Handle an MCP tool call.
	 * Returns response array or throws exception.
	 */
	public function handleToolCall(
		ActiveRow $client,
		ActiveRow $token,
		string $toolName,
		array $params,
		string $clientIp,
	): array
	{
		$startTime = microtime(true);

		// Check rate limit
		$remaining = $this->rateLimitService->checkTokenLimit($token->id);
		if ($remaining < 0) {
			$this->auditService->logMcpCall($client, $token, $toolName, 'rate_limited');
			throw new McpException('Rate limit exceeded', 429);
		}

		// Handle scenario_ prefixed tools
		if (str_starts_with($toolName, 'scenario_')) {
			return $this->handleScenarioCall($client, $token, $toolName, $params, $clientIp, $startTime);
		}

		// Find tool
		$tool = $this->toolRepository->findByName($toolName);
		if (!$tool || !$tool->is_active) {
			$this->auditService->logMcpCall($client, $token, $toolName, 'denied');
			throw new McpException('Unknown tool', 404);
		}

		// Check permission — meta-tools (job status, job list) are always allowed
		$metaTools = ['get_job_status', 'list_my_recent_jobs'];
		if (!in_array($toolName, $metaTools, true) && !$this->authService->hasToolPermission($client->id, $tool->id)) {
			$this->auditService->logMcpCall($client, $token, $toolName, 'denied');
			throw new McpException('Permission denied for this tool', 403);
		}

		// Check IP
		if (!$this->authService->isIpAllowed($client, $clientIp)) {
			$this->auditService->logSecurityEvent('ip_denied', 'denied', $client, ['ip' => $clientIp]);
			throw new McpException('IP not allowed', 403);
		}

		// Validate input
		$schemaFile = $this->getInputSchemaFile($toolName);
		if ($schemaFile) {
			$errors = $this->schemaValidator->validate($params, $schemaFile);
			if ($errors !== null) {
				$this->auditService->logMcpCall($client, $token, $toolName, 'failed', $params, ['errors' => $errors]);
				throw new McpException('Validation failed: ' . implode('; ', $errors), 422);
			}
		}

		// Dispatch based on tool
		$result = match ($toolName) {
			'get_job_status' => $this->handleGetJobStatus($client, $token, $params),
			'list_my_recent_jobs' => $this->handleListRecentJobs($client, $token, $params),
			default => $this->handleGenericTool($client, $token, $tool, $params),
		};

		$durationMs = (int) ((microtime(true) - $startTime) * 1000);
		$this->auditService->logMcpCall(
			$client, $token, $toolName, $result['status'] ?? 'success',
			$params, $result, $result['job_id'] ?? null, $durationMs,
		);

		return $result;
	}


	private function handleCreateTask(ActiveRow $client, ActiveRow $token, array $params): array
	{
		$tool = $this->toolRepository->findByName('create_task');
		$job = $this->jobService->createJob(
			$client->id,
			$client->service_account_id,
			$tool->id,
			$params,
		);

		// Hybrid model: wait up to 20s for completion
		$completed = $this->jobService->waitForCompletion($job->id, 20);

		if ($completed && $completed->status === 'success') {
			$result = json_decode($completed->result, true) ?? [];
			return [
				'mode' => 'done',
				'job_id' => $job->id,
				'task_id' => $result['task_id'] ?? null,
				'status' => 'success',
			];
		}

		return [
			'mode' => 'queued',
			'job_id' => $job->id,
			'status' => $completed?->status ?? 'pending',
		];
	}


	private function handleGetJobStatus(ActiveRow $client, ActiveRow $token, array $params): array
	{
		$job = $this->jobService->findById($params['job_id']);

		if (!$job || $job->client_id !== $client->id) {
			throw new McpException('Job not found', 404);
		}

		$result = [
			'status' => $job->status,
		];

		if ($job->result !== null) {
			$result['result'] = json_decode($job->result, true);
		}
		if ($job->error_message !== null) {
			$result['error'] = $job->error_message;
		}
		if ($job->finished_at !== null) {
			$result['finished_at'] = $job->finished_at->format('c');
		}

		// Include artifacts if any
		$artifacts = $this->artifactService->findByJobId($job->id);
		if ($artifacts) {
			$result['artifacts'] = $this->artifactService->formatForResponse($artifacts);
		}

		return $result;
	}


	private function handleListRecentJobs(ActiveRow $client, ActiveRow $token, array $params): array
	{
		$limit = min($params['limit'] ?? 10, 50);
		$jobs = $this->jobService->getClientJobs(
			$client->id,
			$params['status'] ?? null,
			$params['tool_name'] ?? null,
			$limit,
		);

		$result = [];
		foreach ($jobs as $job) {
			$result[] = [
				'job_id' => $job->id,
				'tool_name' => $job->ref('tools', 'tool_id')?->name ?? '',
				'status' => $job->status,
				'created_at' => $job->created_at->format('c'),
				'finished_at' => $job->finished_at?->format('c'),
			];
		}

		return ['jobs' => $result];
	}


	/**
	 * Generic handler for any tool — creates a job and waits.
	 * Used for tools that don't need special handling in the facade.
	 */
	private function handleGenericTool(ActiveRow $client, ActiveRow $token, ActiveRow $tool, array $params): array
	{
		$job = $this->jobService->createJob(
			$client->id,
			$client->service_account_id,
			$tool->id,
			$params,
		);

		$completed = $this->jobService->waitForCompletion($job->id, 20);

		if ($completed && $completed->status === 'success') {
			$result = json_decode($completed->result, true) ?? [];
			$response = [
				'mode' => 'done',
				'job_id' => $job->id,
				'status' => 'success',
				'result' => $result,
			];

			$artifacts = $this->artifactService->findByJobId($job->id);
			if ($artifacts) {
				$response['artifacts'] = $this->artifactService->formatForResponse($artifacts);
			}

			return $response;
		}

		return [
			'mode' => 'queued',
			'job_id' => $job->id,
			'status' => $completed?->status ?? 'pending',
		];
	}


	/**
	 * Handle scenario_* tool calls from MCP.
	 * Looks up scenario by name, creates run_scenario job with full definition.
	 */
	private function handleScenarioCall(
		ActiveRow $client,
		ActiveRow $token,
		string $toolName,
		array $params,
		string $clientIp,
		float $startTime,
	): array
	{
		$scenarioName = substr($toolName, strlen('scenario_'));
		$scenario = $this->scenarioRepository->findByName($scenarioName);

		if (!$scenario || !$scenario->is_active) {
			$this->auditService->logMcpCall($client, $token, $toolName, 'denied');
			throw new McpException("Scenario not found: {$scenarioName}", 404);
		}

		// Check IP
		if (!$this->authService->isIpAllowed($client, $clientIp)) {
			throw new McpException('IP not allowed', 403);
		}

		// Check permission for run_scenario tool
		$runTool = $this->toolRepository->findByName('run_scenario');
		if ($runTool && !$this->authService->hasToolPermission($client->id, $runTool->id)) {
			$this->auditService->logMcpCall($client, $token, $toolName, 'denied');
			throw new McpException('Permission denied for scenarios', 403);
		}

		// Validate input against scenario's schema
		$inputSchema = json_decode($scenario->input_schema, true);
		if ($inputSchema && !empty($inputSchema['properties'])) {
			$errors = $this->schemaValidator->validateRaw($params, $inputSchema);
			if ($errors !== null) {
				throw new McpException('Validation failed: ' . implode('; ', $errors), 422);
			}
		}

		// Build payload
		$payload = [
			'scenario' => [
				'name' => $scenario->name,
				'description' => $scenario->description,
				'input_schema' => json_decode($scenario->input_schema, true),
				'steps' => json_decode($scenario->steps, true),
			],
			'input' => $params,
		];

		$job = $this->jobService->createJob(
			$client->id,
			$client->service_account_id,
			$runTool->id,
			$payload,
			$scenario->id,
		);

		$completed = $this->jobService->waitForCompletion($job->id, 20);

		$durationMs = (int) ((microtime(true) - $startTime) * 1000);

		if ($completed && in_array($completed->status, ['success', 'awaiting_input'], true)) {
			$result = json_decode($completed->result, true) ?? [];
			$response = [
				'mode' => 'done',
				'job_id' => $job->id,
				'status' => $completed->status,
				'result' => $result,
			];

			$this->auditService->logMcpCall($client, $token, $toolName, $completed->status, $params, $response, $job->id, $durationMs);
			return $response;
		}

		$response = [
			'mode' => 'queued',
			'job_id' => $job->id,
			'status' => $completed?->status ?? 'pending',
		];

		$this->auditService->logMcpCall($client, $token, $toolName, $response['status'], $params, $response, $job->id, $durationMs);
		return $response;
	}


	private function getInputSchemaFile(string $toolName): ?string
	{
		$filename = str_replace('_', '-', $toolName) . '.input.json';
		$path = __DIR__ . '/../../../../../packages/contracts/' . $filename;
		return is_file($path) ? $filename : null;
	}
}
