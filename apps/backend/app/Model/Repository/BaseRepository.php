<?php
declare(strict_types=1);

namespace App\Model\Repository;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

abstract class BaseRepository
{
	public function __construct(
		protected Explorer $database,
	) {
	}


	abstract protected function getTableName(): string;


	public function getTable(): Selection
	{
		return $this->database->table($this->getTableName());
	}


	public function findById(int|string $id): ?ActiveRow
	{
		return $this->getTable()->get($id);
	}
}
