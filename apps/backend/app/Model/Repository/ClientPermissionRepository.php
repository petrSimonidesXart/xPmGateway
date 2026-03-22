<?php
declare(strict_types=1);

namespace App\Model\Repository;

class ClientPermissionRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'client_permissions';
	}


	public function hasPermission(int $clientId, int $toolId): bool
	{
		return (bool) $this->getTable()
			->where('client_id', $clientId)
			->where('tool_id', $toolId)
			->fetch();
	}


	public function getPermittedToolIds(int $clientId): array
	{
		return $this->getTable()
			->where('client_id', $clientId)
			->fetchPairs('tool_id', 'tool_id');
	}


	public function grant(int $clientId, int $toolId): void
	{
		$this->getTable()->insert([
			'client_id' => $clientId,
			'tool_id' => $toolId,
		]);
	}


	public function revoke(int $clientId, int $toolId): void
	{
		$this->getTable()
			->where('client_id', $clientId)
			->where('tool_id', $toolId)
			->delete();
	}


	public function syncPermissions(int $clientId, array $toolIds): void
	{
		$this->getTable()->where('client_id', $clientId)->delete();
		foreach ($toolIds as $toolId) {
			$this->grant($clientId, (int) $toolId);
		}
	}
}
