<?php
declare(strict_types=1);

namespace App\Model\Service;

use Nette\Database\Explorer;

class WorkerStatusService
{
	private const OFFLINE_THRESHOLD_SECONDS = 30;


	public function __construct(
		private Explorer $database,
	) {
	}


	/**
	 * Record a heartbeat from the worker (called on each poll cycle).
	 * If the worker was previously offline (>30s gap), resets started_at.
	 */
	public function recordHeartbeat(string $workerId = 'main'): void
	{
		$this->database->query(
			'INSERT INTO worker_heartbeats (worker_id, last_seen_at, started_at) VALUES (?, NOW(), NOW())
			ON DUPLICATE KEY UPDATE
				started_at = IF(TIMESTAMPDIFF(SECOND, last_seen_at, NOW()) > ?, NOW(), started_at),
				last_seen_at = NOW()',
			$workerId,
			self::OFFLINE_THRESHOLD_SECONDS,
		);
	}


	/**
	 * Get the combined worker status for the admin UI.
	 *
	 * @return array{status: string, online: bool, last_seen_at: ?string, started_at: ?string, job: ?array}
	 */
	public function getStatus(): array
	{
		$heartbeat = $this->database->table('worker_heartbeats')
			->where('worker_id', 'main')
			->fetch();

		$processingJob = $this->database->table('jobs')
			->where('status', 'processing')
			->order('started_at DESC')
			->fetch();

		$isRecentHeartbeat = $heartbeat
			&& (new \DateTime)->getTimestamp() - $heartbeat->last_seen_at->getTimestamp() < self::OFFLINE_THRESHOLD_SECONDS;

		$jobInfo = null;
		if ($processingJob) {
			$jobInfo = [
				'id' => $processingJob->id,
				'tool_name' => $processingJob->ref('tools', 'tool_id')->name ?? '?',
				'client_name' => $processingJob->ref('clients', 'client_id')->name ?? '?',
				'started_at' => $processingJob->started_at->format('c'),
				'duration_seconds' => (new \DateTime)->getTimestamp() - $processingJob->started_at->getTimestamp(),
			];
		}

		if ($processingJob) {
			$status = 'busy';
		} elseif ($isRecentHeartbeat) {
			$status = 'idle';
		} elseif ($heartbeat) {
			$status = 'offline';
		} else {
			$status = 'unknown';
		}

		return [
			'status' => $status,
			'online' => $status === 'idle' || $status === 'busy',
			'last_seen_at' => $heartbeat?->last_seen_at?->format('c'),
			'started_at' => $heartbeat?->started_at?->format('c'),
			'uptime_seconds' => $heartbeat?->started_at
				? (new \DateTime)->getTimestamp() - $heartbeat->started_at->getTimestamp()
				: null,
			'job' => $jobInfo,
		];
	}
}
