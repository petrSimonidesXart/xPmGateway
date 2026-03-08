<?php
declare(strict_types=1);

namespace App\Model\Repository;

class ServiceAccountRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'service_accounts';
	}


	public function findAllActive(): array
	{
		return $this->getTable()
			->where('is_active', true)
			->fetchAll();
	}
}
