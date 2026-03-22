<?php
declare(strict_types=1);

namespace App\Model\Repository;

use Nette\Database\Table\ActiveRow;

class ArtifactRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'job_artifacts';
	}


	public function create(array $data): ActiveRow
	{
		$data['id'] = $this->generateUuid();
		return $this->getTable()->insert($data);
	}


	/**
	 * @return ActiveRow[]
	 */
	public function findByJobId(string $jobId): array
	{
		return $this->getTable()
			->where('job_id', $jobId)
			->order('created_at ASC')
			->fetchAll();
	}


	private function generateUuid(): string
	{
		$data = random_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
}
