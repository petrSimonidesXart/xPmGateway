<?php
declare(strict_types=1);

namespace App\Model\Repository;

class RateLimitRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'rate_limits';
	}


	/**
	 * Increment hit counter for a key within a sliding window.
	 * Returns current hit count after increment.
	 */
	public function incrementAndGet(string $key, int $windowSeconds): int
	{
		$now = new \DateTime();
		$windowStart = new \DateTime("-{$windowSeconds} seconds");

		$row = $this->getTable()
			->where('key', $key)
			->where('window_start >= ?', $windowStart)
			->fetch();

		if ($row) {
			$this->getTable()
				->where('id', $row->id)
				->update(['hits+=' => 1]);
			return $row->hits + 1;
		}

		// Clean old entries for this key
		$this->getTable()
			->where('key', $key)
			->where('window_start < ?', $windowStart)
			->delete();

		// Create new window
		$this->getTable()->insert([
			'key' => $key,
			'hits' => 1,
			'window_start' => $now,
		]);

		return 1;
	}


	public function getHits(string $key, int $windowSeconds): int
	{
		$windowStart = new \DateTime("-{$windowSeconds} seconds");

		$row = $this->getTable()
			->where('key', $key)
			->where('window_start >= ?', $windowStart)
			->fetch();

		return $row ? $row->hits : 0;
	}


	public function cleanup(): int
	{
		return $this->getTable()
			->where('window_start < ?', new \DateTime('-1 hour'))
			->delete();
	}
}
