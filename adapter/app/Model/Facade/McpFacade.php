<?php
declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Repository\ToolRepository;
use App\Model\Service\AuthService;
use App\Model\Service\AuditService;
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

		// Find tool
		$tool = $this->toolRepository->findByName($toolName);
		if (!$tool || !$tool->is_active) {
			$this->auditService->logMcpCall($client, $token, $toolName, 'denied');
			throw new McpException('Unknown tool', 404);
		}

		// Check permission
		if (!$this->authService->hasToolPermission($client->id, $tool->id)) {
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
			'create_task' => $this->handleCreateTask($client, $token, $params),
			'get_job_status' => $this->handleGetJobStatus($client, $token, $params),
			'list_my_recent_jobs' => $this->handleListRecentJobs($client, $token, $params),
			default => throw new McpException('Tool not implemented', 501),
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
		$job = $this->jobService->jobRepository->findById($params['job_id']);

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


	private function getInputSchemaFile(string $toolName): ?string
	{
		$map = [
			'create_task' => 'create-task.input.json',
			'get_job_status' => 'get-job-status.input.json',
			'list_my_recent_jobs' => 'list-my-recent-jobs.input.json',
		];
		return $map[$toolName] ?? null;
	}
}
