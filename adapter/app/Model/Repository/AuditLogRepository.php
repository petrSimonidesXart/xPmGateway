<?php
declare(strict_types=1);

namespace App\Model\Repository;

use Nette\Database\Table\Selection;

class AuditLogRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'audit_log';
	}


	public function log(array $data): void
	{
		$this->getTable()->insert($data);
	}


	public function findFiltered(
		?int $clientId = null,
		?string $action = null,
		?\DateTime $dateFrom = null,
		?\DateTime $dateTo = null,
		int $limit = 50,
		int $offset = 0,
	): Selection
	{
		$query = $this->getTable()
			->order('created_at DESC')
			->limit($limit, $offset);

		if ($clientId !== null) {
			$query->where('client_id', $clientId);
		}
		if ($action !== null) {
			$query->where('action', $action);
		}
		if ($dateFrom !== null) {
			$query->where('created_at >= ?', $dateFrom);
		}
		if ($dateTo !== null) {
			$query->where('created_at <= ?', $dateTo);
		}

		return $query;
	}
}
