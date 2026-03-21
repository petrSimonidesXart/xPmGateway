<?php
declare(strict_types=1);

namespace App\Model\Repository;

use Nette\Database\Table\ActiveRow;

class AdminUserRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'admin_users';
	}


	public function findByUsername(string $username): ?ActiveRow
	{
		return $this->getTable()
			->where('username', $username)
			->where('is_active', true)
			->fetch() ?: null;
	}


	public function updateLastLogin(int $id): void
	{
		$this->getTable()
			->where('id', $id)
			->update(['last_login_at' => new \DateTime]);
	}
}
