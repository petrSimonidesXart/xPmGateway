<?php
declare(strict_types=1);

namespace App\Model\Service;

use App\Model\Repository\AuditLogRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IRequest;

class AuditService
{
	public function __construct(
		private AuditLogRepository $auditLogRepository,
		private IRequest $httpRequest,
	) {
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
	): void
	{
		$this->log(
			action: 'mcp_call',
			client: $client,
			tokenId: $token?->id,
			toolName: $toolName,
			resultStatus: $resultStatus,
			payload: $payload,
			resultData: $resultData,
			jobId: $jobId,
			durationMs: $durationMs,
		);
	}


	public function logAdminAction(
		string $action,
		string $resultStatus = 'success',
		?array $payload = null,
		?array $resultData = null,
	): void
	{
		$this->log(
			action: $action,
			resultStatus: $resultStatus,
			payload: $payload,
			resultData: $resultData,
		);
	}


	public function logSecurityEvent(
		string $action,
		string $resultStatus,
		?ActiveRow $client = null,
		?array $payload = null,
	): void
	{
		$this->log(
			action: $action,
			client: $client,
			resultStatus: $resultStatus,
			payload: $payload,
		);
	}


	private function log(
		string $action,
		?ActiveRow $client = null,
		?int $tokenId = null,
		string $toolName = '',
		string $resultStatus = 'success',
		?array $payload = null,
		?array $resultData = null,
		?string $jobId = null,
		?int $durationMs = null,
	): void
	{
		$this->auditLogRepository->log([
			'client_id' => $client?->id,
			'client_name' => $client?->name ?? '',
			'api_token_id' => $tokenId,
			'tool_name' => $toolName,
			'action' => $action,
			'payload' => $payload ? json_encode($payload) : null,
			'result_status' => $resultStatus,
			'result_data' => $resultData ? json_encode($resultData) : null,
			'job_id' => $jobId,
			'ip_address' => $this->httpRequest->getRemoteAddress() ?? '0.0.0.0',
			'user_agent' => $this->httpRequest->getHeader('User-Agent'),
			'duration_ms' => $durationMs,
		]);
	}
}
