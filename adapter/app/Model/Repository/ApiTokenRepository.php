<?php
declare(strict_types=1);

namespace App\Model\Repository;

use Nette\Database\Table\ActiveRow;

class ApiTokenRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'api_tokens';
	}


	public function findByHash(string $tokenHash): ?ActiveRow
	{
		return $this->getTable()
			->where('token_hash', $tokenHash)
			->where('revoked_at', null)
			->fetch() ?: null;
	}


	public function findActiveByClientId(int $clientId): array
	{
		return $this->getTable()
			->where('client_id', $clientId)
			->where('revoked_at', null)
			->order('created_at DESC')
			->fetchAll();
	}


	public function revoke(int $id): void
	{
		$this->getTable()
			->where('id', $id)
			->update(['revoked_at' => new \DateTime()]);
	}


	public function updateLastUsed(int $id): void
	{
		$this->getTable()
			->where('id', $id)
			->update(['last_used_at' => new \DateTime()]);
	}
}
