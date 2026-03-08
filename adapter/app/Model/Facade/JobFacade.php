<?php
declare(strict_types=1);

namespace App\Model\Facade;

use App\Model\Service\AlertService;
use App\Model\Service\EncryptionService;
use App\Model\Service\JobService;
use Nette\Database\Table\ActiveRow;

class JobFacade
{
	public function __construct(
		private JobService $jobService,
		private EncryptionService $encryptionService,
		private AlertService $alertService,
	) {
	}


	/**
	 * Get next pending job for worker with decrypted credentials.
	 */
	public function getNextJobForWorker(): ?array
	{
		$job = $this->jobService->getNextPendingJob();
		if (!$job) {
			return null;
		}

		// Atomically mark as processing
		if (!$this->jobService->markProcessing($job->id)) {
			return null; // Another worker grabbed it
		}

		$serviceAccount = $job->ref('service_accounts', 'service_account_id');

		return [
			'id' => $job->id,
			'tool_name' => $job->ref('tools', 'tool_id')?->name,
			'payload' => json_decode($job->payload, true),
			'service_account' => [
				'username' => $serviceAccount->username,
				'password' => $this->encryptionService->decrypt($serviceAccount->password_encrypted),
			],
			'attempt' => $job->attempts,
			'timeout_seconds' => $job->timeout_seconds,
		];
	}


	/**
	 * Process job result from worker.
	 */
	public function handleJobResult(string $jobId, string $status, ?array $result = null, ?string $error = null, ?array $screenshots = null): void
	{
		$job = $this->jobService->jobRepository->findById($jobId);
		if (!$job) {
			throw new McpException('Job not found', 404);
		}

		if ($status === 'success') {
			$this->jobService->completeJob($jobId, $result ?? [], $screenshots);
		} else {
			$this->jobService->failJob($jobId, $error ?? 'Unknown error', $screenshots);

			// Refresh job to get updated state
			$job = $this->jobService->jobRepository->findById($jobId);
			if ($job && $job->status === 'failed') {
				$this->alertService->sendJobFailedAlert($job);
			}
		}
	}
}
