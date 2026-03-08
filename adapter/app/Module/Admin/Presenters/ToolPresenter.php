<?php
declare(strict_types=1);

namespace App\Module\Admin\Presenters;

use App\Model\Repository\ToolRepository;

class ToolPresenter extends BasePresenter
{
	public function __construct(
		private ToolRepository $toolRepository,
	) {
		parent::__construct();
	}


	public function renderDefault(): void
	{
		$this->template->tools = $this->toolRepository->getTable()
			->order('name ASC')
			->fetchAll();
	}


	public function handleToggle(int $id): void
	{
		$this->requireAdmin();

		$tool = $this->toolRepository->findById($id);
		if (!$tool) {
			$this->error('Tool not found');
		}

		$this->toolRepository->getTable()
			->where('id', $id)
			->update(['is_active' => !$tool->is_active]);

		$this->auditService->logAdminAction('tool_toggled', 'success', [
			'tool_id' => $id,
			'is_active' => !$tool->is_active,
		]);

		$this->flashMessage($tool->is_active ? 'Tool deaktivován.' : 'Tool aktivován.');
		$this->redirect('this');
	}
}
