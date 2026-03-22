<?php
declare(strict_types=1);

namespace App\Model\Repository;

class PmLookupRepository extends BaseRepository
{
	protected function getTableName(): string
	{
		return 'pm_lookups';
	}


	/** @return array<string, array{value: string, description: ?string}> */
	public function getByCategory(string $category): array
	{
		$rows = $this->getTable()
			->where('category', $category)
			->order('sort_order ASC')
			->fetchAll();

		$result = [];
		foreach ($rows as $row) {
			$result[$row->shortcut] = [
				'value' => $row->value,
				'description' => $row->description,
			];
		}
		return $result;
	}


	/**
	 * Resolve a shortcut to full value. If not found, returns the input as-is.
	 */
	public function resolve(string $category, string $input): string
	{
		$upper = strtoupper(trim($input));
		$row = $this->getTable()
			->where('category', $category)
			->where('shortcut', $upper)
			->fetch();

		return $row ? $row->value : $input;
	}


	/** @return array<array{category: string, shortcut: string, value: string, description: ?string}> */
	public function getAll(): array
	{
		return $this->getTable()->order('category, sort_order ASC')->fetchAll();
	}
}
