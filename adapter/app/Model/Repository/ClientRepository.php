<?php
declare(strict_types=1);

namespace App\Model\Repository;


class ClientRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'clients';
	}


	public function findAllActive(): array
	{
		return $this->getTable()
			->where('is_active', true)
			->fetchAll();
	}
}
