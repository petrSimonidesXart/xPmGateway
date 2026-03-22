<?php
declare(strict_types=1);

namespace App\Model\Repository;

use Nette\Database\Table\ActiveRow;

class ToolRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'tools';
	}


	public function findByName(string $name): ?ActiveRow
	{
		return $this->getTable()
			->where('name', $name)
			->fetch() ?: null;
	}


	public function findAllActive(): array
	{
		return $this->getTable()
			->where('is_active', true)
			->fetchAll();
	}
}
