<?php
declare(strict_types=1);

namespace App\Model\Service;

use App\Model\Repository\JobRepository;
use App\Model\Repository\ToolRepository;
use Nette\Database\Table\ActiveRow;

class JobService
{
	public function __construct(
		private JobRepository $jobRepository,
		private ToolRepository $toolRepository,
	) {
	}


	public function createJob(int $clientId, int $serviceAccountId, int $toolId, array $payload): ActiveRow
	{
		return $this->jobRepository->create([
			'client_id' => $clientId,
			'service_account_id' => $serviceAccountId,
			'tool_id' => $toolId,
			'payload' => json_encode($payload),
			'status' => 'pending',
		]);
	}


	/**
	 * Wait for job completion with polling (hybrid response model).
	 * Returns the job row if completed within timeout, null otherwise.
	 */
	public function waitForCompletion(string $jobId, int $timeoutSeconds = 20): ?ActiveRow
	{
		$deadline = time() + $timeoutSeconds;

		while (time() < $deadline) {
			$job = $this->jobRepository->findById($jobId);
			if ($job && in_array($job->status, ['success', 'failed'], true)) {
				return $job;
			}
			usleep(500_000); // 500ms
		}

		return null;
	}


	public function getNextPendingJob(): ?ActiveRow
	{
		return $this->jobRepository->findNextPending();
	}


	public function markProcessing(string $jobId): bool
	{
		return $this->jobRepository->markProcessing($jobId);
	}


	public function completeJob(string $jobId, array $result, ?array $screenshots = null): void
	{
		$this->jobRepository->markSuccess($jobId, $result, $screenshots);
	}


	public function failJob(string $jobId, string $error, ?array $screenshots = null): void
	{
		$this->jobRepository->markFailed($jobId, $error, $screenshots);
	}


	/**
	 * Mark timed-out jobs and reset eligible ones for retry.
	 */
	public function processTimeouts(): array
	{
		$timedOut = $this->jobRepository->markTimedOut();
		$reset = $this->jobRepository->resetTimedOutForRetry();
		return ['timed_out' => $timedOut, 'reset_for_retry' => $reset];
	}


	public function getClientJobs(int $clientId, ?string $status = null, ?string $toolName = null, int $limit = 10): array
	{
		return $this->jobRepository->findByClientId($clientId, $status, $toolName, $limit);
	}
}
