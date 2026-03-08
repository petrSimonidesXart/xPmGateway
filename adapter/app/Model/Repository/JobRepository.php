<?php
declare(strict_types=1);

namespace App\Model\Repository;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Random;

class JobRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'jobs';
	}


	public function create(array $data): ActiveRow
	{
		$data['id'] = $this->generateUuid();
		return $this->getTable()->insert($data);
	}


	public function findNextPending(): ?ActiveRow
	{
		return $this->getTable()
			->where('status', 'pending')
			->order('created_at ASC')
			->limit(1)
			->fetch() ?: null;
	}


	public function markProcessing(string $id): bool
	{
		return (bool) $this->getTable()
			->where('id', $id)
			->where('status', 'pending')
			->update([
				'status' => 'processing',
				'started_at' => new \DateTime(),
				'attempts+=' => 1,
			]);
	}


	public function markSuccess(string $id, array $result, ?array $screenshots = null): void
	{
		$this->getTable()
			->where('id', $id)
			->update([
				'status' => 'success',
				'result' => json_encode($result),
				'screenshots' => $screenshots ? json_encode($screenshots) : null,
				'finished_at' => new \DateTime(),
			]);
	}


	public function markFailed(string $id, string $errorMessage, ?array $screenshots = null): void
	{
		$this->getTable()
			->where('id', $id)
			->update([
				'status' => 'failed',
				'error_message' => $errorMessage,
				'screenshots' => $screenshots ? json_encode($screenshots) : null,
				'finished_at' => new \DateTime(),
			]);
	}


	public function markTimedOut(): int
	{
		return $this->getTable()
			->where('status', 'processing')
			->where('started_at < ?', new \DateTime('-120 seconds'))
			->update([
				'status' => 'timeout',
				'finished_at' => new \DateTime(),
			]);
	}


	public function resetTimedOutForRetry(): int
	{
		return $this->getTable()
			->where('status', 'timeout')
			->where('attempts < max_attempts')
			->update([
				'status' => 'pending',
				'started_at' => null,
			]);
	}


	public function findByClientId(int $clientId, ?string $status = null, ?string $toolName = null, int $limit = 10): array
	{
		$query = $this->getTable()
			->where('client_id', $clientId)
			->order('created_at DESC')
			->limit($limit);

		if ($status !== null) {
			$query->where('status', $status);
		}

		if ($toolName !== null) {
			$query->where(':tools.name', $toolName);
		}

		return $query->fetchAll();
	}


	private function generateUuid(): string
	{
		$data = random_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant RFC 4122
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
}
