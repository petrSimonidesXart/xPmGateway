<?php
declare(strict_types=1);

namespace App\Model\Repository;

use Nette\Database\Table\ActiveRow;

class ScenarioRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'scenarios';
	}


	public function findByName(string $name): ?ActiveRow
	{
		return $this->getTable()->where('name', $name)->fetch() ?: null;
	}


	/** @return ActiveRow[] */
	public function findAllActive(): array
	{
		return $this->getTable()
			->where('is_active', true)
			->order('name ASC')
			->fetchAll();
	}
}
